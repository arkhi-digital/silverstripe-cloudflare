
<div class="cms-content center $BaseCSSClasses" data-layout-type="border" data-pjax-fragment="Content">
    <div class="cms-content-header north">
        <div class="cms-content-header-info">
            <% include CMSBreadcrumbs %>
        </div>
    </div>

    <div class="cms-content-fields cms-panel-padded center">
        <% if $CredentialsDefined %>
            <% if $CFAlert.Message %><div class="cloudflare-message message {$CFAlert.Type}">$CFAlert.Message</div>$DestroyCFAlert<% end_if %>
            <% if $isReady %>
            <div class="cloudflare-panel">
                <div class="cloudflare-panel-title"><%t CloudFlare.QuickActionsLabel "Quick Actions" %></div>
                <div class="cloudflare-panel-actions">
                    <a href="{$Link('purge-all')}" class="ss-ui-button"><%t CloudFlare.PurgeAllButton "Purge All" %></a>
                    <a href="{$Link('purge-css')}" class="ss-ui-button"><%t CloudFlare.PurgeStylesheetsButton "Purge Stylesheets" %></a>
                    <a href="{$Link('purge-javascript')}" class="ss-ui-button"><%t CloudFlare.PurgeJavascriptButton "Purge Javascript" %></a>
                    <a href="{$Link('purge-images')}" class="ss-ui-button"><%t CloudFlare.PurgeImagesButton "Purge Images" %></a>
                </div>
            </div>

            <div class="cloudflare-panel">
                <div class="cloudflare-panel-title"><%t CloudFlare.SingleFileLabel "Single File / URL" %></div>
                $FormSingleUrlForm
            </div>
            <% end_if %>
        <% else %>
            <div class="cloudflare-message message">
                <%t CloudFlare.NoCredentialsDefined "No CloudFlare credentials have been defined. Please see the readme to find out more." %>
            </div>
        <% end_if %>
    </div>

    <div class="cms-content-actions cms-content-controls south text-center">
        <%t CloudFlare.ModuleFooterLabel "CloudFlare Module" %> - <a href="//www.steadlane.com.au" target="_blank">Stead Lane</a> - BSD (3-Clause)<% if $CredentialsDefined %> | <%t CloudFlare.ZoneID "Zone ID" %>: $ZoneId <% end_if %>
    </div>
</div>
