<?php
/**
 * expwt/field/<object>/<version>/<language>/<identifier>: one field of the user's draft in the editor the installation
 * has for its datatype, shown by the sidebar in a frame (decision 12, F1). Rich text (ezxmltext) gets the configured
 * editor (ezoe with TinyMCE 8, or exp_oe_tiptap) with its toolbar, custom tags, embeds and link and object browsing;
 * eztags its tag picker; an image its upload; every other datatype its own edit template ({attribute_edit_gui}).
 *
 * Saving runs the steps content/edit runs for an attribute, in the same order and with the same functions
 * (eZContentObject::validateInput(), fixupInput(), fetchInput(), storeInput(), the edit handlers' input handlers, the
 * object's name), so what is stored is what the administration stores for the same input. A custom action of the
 * datatype (an engine switch, removing an image, browsing for an object) runs as there too.
 *
 * <version> 0: the user's newest draft in <language>, or a new one. The page tells the sidebar (expwt-field.js,
 * postMessage to the parent window of the same origin) which draft it is and, after a save, the field's new value.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */

$Module = $Params['Module'];
$http = eZHTTPTool::instance();
$tpl = eZTemplate::factory();
$base = 'ContentObjectAttribute';

$object = eZContentObject::fetch( (int)$Params['ObjectID'] );
$language = preg_replace( '/[^A-Za-z-]/', '', (string)$Params['Language'] );
$identifier = preg_replace( '/[^A-Za-z0-9_]/', '', (string)$Params['Identifier'] );
if ( !$object instanceof eZContentObject || $language === '' || $identifier === '' )
    return $Module->handleError( eZError::KERNEL_NOT_AVAILABLE, 'kernel' );
if ( !$object->canEdit( false, false, false, $language ) )
    return $Module->handleError( eZError::KERNEL_ACCESS_DENIED, 'kernel' );
$userId = (int)eZUser::currentUserID();

// the draft: the one asked for when it is the user's, else the user's newest in the language, else a new one
$version = null;
$asked = (int)$Params['Version'];
if ( $asked > 0 )
{
    $candidate = $object->version( $asked );
    if ( $candidate instanceof eZContentObjectVersion && (int)$candidate->attribute( 'creator_id' ) === $userId
         && in_array( (int)$candidate->attribute( 'status' ), array( eZContentObjectVersion::STATUS_DRAFT, eZContentObjectVersion::STATUS_INTERNAL_DRAFT ), true ) )
        $version = $candidate;
}
if ( !$version )
{
    foreach ( (array)$object->versions() as $candidate )
    {
        if ( (int)$candidate->attribute( 'creator_id' ) === $userId && (int)$candidate->attribute( 'status' ) === eZContentObjectVersion::STATUS_DRAFT
             && (string)$candidate->initialLanguageCode() === $language
             && ( !$version || (int)$candidate->attribute( 'version' ) > (int)$version->attribute( 'version' ) ) )
            $version = $candidate;
    }
}
if ( !$version )
{
    $db = eZDB::instance();
    $db->begin();
    $existing = in_array( $language, $object->availableLanguages(), true );
    $version = $object->createNewVersionIn( $language, $existing ? false : (string)$object->attribute( 'initial_language_code' ) );
    $version->setAttribute( 'status', eZContentObjectVersion::STATUS_DRAFT );
    $version->store();
    $db->commit();
}
$versionNumber = (int)$version->attribute( 'version' );

$attribute = null;
foreach ( (array)$version->contentObjectAttributes( $language ) as $candidate )
{
    if ( (string)$candidate->attribute( 'contentclass_attribute_identifier' ) === $identifier )
        $attribute = $candidate;
}
if ( !$attribute )
    return $Module->handleError( eZError::KERNEL_NOT_AVAILABLE, 'kernel' );
$attributes = array( $attribute );
$selfUri = 'expwt/field/' . (int)$object->attribute( 'id' ) . '/' . $versionNumber . '/' . $language . '/' . $identifier;

$saved = false;
$validation = array( 'processed' => false, 'attributes' => array() );
if ( $http->hasPostVariable( 'StoreButton' ) || $http->hasPostVariable( 'CustomActionButton' ) )
{
    $class = $object->contentClass();
    // as content/edit (kernel/content/attribute_edit.php): CustomActionButton[<attribute id>_<action>]
    $customActions = array();
    if ( $http->hasPostVariable( 'CustomActionButton' ) )
    {
        foreach ( (array)$http->postVariable( 'CustomActionButton' ) as $key => $value )
        {
            if ( preg_match( '#^([0-9]+)_(.*)$#', (string)$key, $m ) && (int)$m[1] === (int)$attribute->attribute( 'id' ) )
                $customActions[$m[1]] = array( 'id' => $m[1], 'value' => $m[2] );
        }
    }
    // as content/edit: a draft saved, or a custom action of a datatype, does not check required fields yet
    $result = $object->validateInput( $attributes, $base, false, array( 'skip-isRequired' => true ) );
    if ( $result['require-fixup'] )
        $object->fixupInput( $attributes, $base );
    $handlers = eZContentObjectEditHandler::validateInputHandlers( $Module, $class, $object, $version, $attributes, $versionNumber, $language, false, array( 'skip-isRequired' => true ) );
    $valid = $result['input-validated'] && $handlers['validated'];
    eZContentObjectEditHandler::executeInputHandlers( $Module, $class, $object, $version, $attributes, $versionNumber, $language, false );
    $fetch = $object->fetchInput( $attributes, $base, $customActions,
                                  array( 'module' => $Module, 'current-redirection-uri' => $selfUri ) );
    if ( !empty( $fetch['attribute-input-map'] ) )
    {
        $db = eZDB::instance();
        $db->begin();
        $version->setAttribute( 'modified', time() );
        $version->store();
        $object->storeInput( $attributes, $fetch['attribute-input-map'] );
        $db->commit();
        ezpEvent::getInstance()->notify( 'content/cache/version', array( $object->attribute( 'id' ), $versionNumber ) );
    }
    // a custom action ran inside fetchInput() as in content/edit; one that browses for an object redirects
    if ( $Module->exitStatus() == eZModule::STATUS_REDIRECT )
        return;
    $db = eZDB::instance();
    $db->begin();
    $object->setName( $class->contentObjectName( $object, $versionNumber, $language ), $versionNumber, $language );
    $db->commit();
    $validation = array( 'processed' => true, 'attributes' => $result['unvalidated-attributes'] );
    $saved = $valid && $http->hasPostVariable( 'StoreButton' );
    // the stored attribute again, as the next edit and the sidebar see it
    foreach ( (array)$version->contentObjectAttributes( $language ) as $candidate )
    {
        if ( (int)$candidate->attribute( 'id' ) === (int)$attribute->attribute( 'id' ) )
            $attribute = $candidate;
    }
}

$theme = $http->hasGetVariable( 'theme' ) && in_array( $http->getVariable( 'theme' ), array( 'light', 'dark' ), true ) ? $http->getVariable( 'theme' ) : '';
$tpl->setVariable( 'object', $object );
$tpl->setVariable( 'version', $version );
$tpl->setVariable( 'attribute', $attribute );
$tpl->setVariable( 'edit_language', $language );
$tpl->setVariable( 'validation', $validation );
$tpl->setVariable( 'saved', $saved );
$tpl->setVariable( 'form_action', $selfUri . ( $theme !== '' ? '?theme=' . $theme : '' ) );
$tpl->setVariable( 'theme', $theme );
$tpl->setVariable( 'message', json_encode( array(
    'expwt' => $saved ? 'field-saved' : 'field-ready', 'identifier' => $identifier, 'objectId' => (int)$object->attribute( 'id' ),
    'version' => $versionNumber, 'language' => $language, 'value' => $saved ? expwtFieldValue::of( $attribute ) : null,
), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP ) );

return array( 'content' => $tpl->fetch( 'design:expwt/field.tpl' ),
              'pagelayout' => 'design:expwt/field_pagelayout.tpl',
              'path' => array( array( 'text' => (string)$attribute->contentClassAttributeName(), 'url' => false ) ) );
