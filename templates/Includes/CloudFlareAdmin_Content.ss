<div class="cms-content flexbox-area-grow fill-height center $BaseCSSClasses" data-layout-type="border" data-pjax-fragment="Content">
    <div class="cms-content-header north">
        <div class="cms-content-header-info">
            <% include SilverStripe\\Admin\\CMSBreadcrumbs %>
        </div>
    </div>

    <div class="flexbox-area-grow fill-height">
        <div class="cms-content-fields cms-panel-padded center">
            <% if $HasAnyAccess %>
                <% if $CredentialsDefined %>
                    <% if $CFAlert.Message %>
                        <div class="cloudflare-message message {$CFAlert.Type}">$CFAlert.Message</div>$DestroyCFAlert<% end_if %>
                    <% if $isReady %>
                        <% if $HasPermission('CF_PURGE_ALL') || $HasPermission('CF_PURGE_STYLESHEETS') || $HasPermission('CF_PURGE_JAVASCRIPT') || $HasPermission('CF_PURGE_IMAGES') %>
                            <div class="cloudflare-panel">
                                <div class="cloudflare-panel-title"><%t CloudFlare.QuickActionsLabel "Quick Actions" %></div>
                                <div class="cloudflare-panel-actions">
                                    <% if $HasPermission('CF_PURGE_ALL') %><a href="{$Link('purge-all')}" class="cf-button"><%t CloudFlare.PurgeAllButton "Purge All" %></a><% end_if %>
                                    <% if $HasPermission('CF_PURGE_STYLESHEETS') %><a href="{$Link('purge-stylesheets')}" class="cf-button"><%t CloudFlare.PurgeStylesheetsButton "Purge Stylesheets" %></a><% end_if %>
                                    <% if $HasPermission('CF_PURGE_JAVASCRIPT') %><a href="{$Link('purge-javascript')}" class="cf-button"><%t CloudFlare.PurgeJavascriptButton "Purge Javascript" %></a><% end_if %>
                                    <% if $HasPermission('CF_PURGE_IMAGES') %><a href="{$Link('purge-images')}" class="cf-button"><%t CloudFlare.PurgeImagesButton "Purge Images" %></a><% end_if %>
                                </div>
                            </div>
                        <% end_if %>

                        <% if $HasPermission('CF_PURGE_SINGLE') %>
                            <div class="cloudflare-panel">
                                <div class="cloudflare-panel-title"><%t CloudFlare.SingleFileLabel "Single File / URL" %></div>
                                $FormSingleUrlForm
                            </div>
                        <% end_if %>
                    <% end_if %>
                <% else %>
                    <div class="cloudflare-message message">
                        <%t CloudFlare.NoCredentialsDefined "No CloudFlare credentials have been defined. Please see the readme to find out more." %>
                    </div>
                <% end_if %>

            <% else %>
                <div class="cloudflare-message message error">You don't have permission to use this module.</div>
            <% end_if %>
        </div>
    </div>
    <div class="cms-content-actions cms-content-controls south text-center">
        <%t CloudFlare.ModuleFooterLabel "CloudFlare Module" %> - <a href="//www.steadlane.com.au" target="_blank">Stead
        Lane</a> - BSD (3-Clause)<% if $CredentialsDefined %> | <%t CloudFlare.ZoneID "Zone ID" %>: $ZoneId <% end_if %>
    </div>
</div>
