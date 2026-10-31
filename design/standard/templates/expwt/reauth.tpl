{* expwt/reauth: shown after the kernel's "Verify it's you" was answered; expwt-reauth.js tells the sidebar and
   closes the window. *}
<div class="expwt-reauth-done" data-expwt-reauth="{$action|wash}">
    <p role="status">{'Thank you. You can go back to the page; this window closes by itself.'|i18n( 'extension/expwt' )}</p>
</div>
{ezscript( array( 'expwt-reauth.js' ) )}
