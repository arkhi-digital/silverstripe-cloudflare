
<div id="pages-controller-cms-content" class="has-panel cms-content flexbox-area-grow fill-width fill-height <% if not $isReady %>cf-not-ready<% end_if %> $BaseCSSClasses" data-layout-type="border" data-pjax-fragment="Content" data-ignore-tab-state="true">
    $Tools

    <div class="fill-height flexbox-area-grow">
        <div class="cms-content-header north">
            <div class="cms-content-header-info flexbox-area-grow vertical-align-items fill-width">
                <% if $BreadcrumbsBackLink %><a href="$BreadcrumbsBackLink" class="btn btn-secondary btn--no-text font-icon-left-open-big hidden-lg-up toolbar__back-button"></a><% end_if %>
                <% include SilverStripe\\Admin\\CMSBreadcrumbs %>
            </div>

            <div class="cms-content-header-tabs cms-tabset">
            </div>
        </div>

        <% if not $CredentialsDefined && $HasAnyAccess %>
        <div class="fill-width campaign-info cloudflare-help" style="<% if $ShowHelp %>display:none;<% end_if %>">
            <div class="campaign-info__icon"><span class="font-icon-white-question icon btn--icon-xl"></span></div>
            <div class="flexbox-area-grow campaign-info__content">
                <h3>Cloudflare for SilverStripe Module</h3>
                <p>To connect your SilverStripe website with your Cloudflare account, add your Cloudflare credentials to the site's app/_config.php or mysite/_config.php:</p>
                <p>
                    <b>define('CLOUDFLARE_AUTH_EMAIL', 'your@cloudflarelogin.email');</b><br>
                    <b>define('CLOUDFLARE_AUTH_KEY', 'yourcloudflareauthkey');</b>
                </p>
                <p>Or alternatively, add your Cloudflare credentials to the site's .env file:</p>
                <p>
                    <b>CLOUDFLARE_AUTH_EMAIL="your@cloudflarelogin.email"</b><br>
                    <b>CLOUDFLARE_AUTH_KEY="yourcloudflareauthkey"</b>
                </p>
            </div>
            <div class="display-1"><i class="font-icon-upload"></i></div>
        </div>
        <% end_if %>


        <div class="flexbox-area-grow fill-height">

            <div style="padding:2rem; clear:both;">

            <% if $HasAnyAccess %>
                <% if $CredentialsDefined %>
                    <% if $CFAlert.Message %><div class="cloudflare-message mb-5 message {$CFAlert.Type} <% if $CFAlert.Type=='success' %>good<% end_if %> d-flex align-items-center">
                        <div class="display-1 pr-4 <% if $CFAlert.Type=='success' || $CFAlert.Type=='good' %>text-success<% else_if $CFAlert.Type=='warning' %>text-warning<% else_if $CFAlert.Type=='bad' || $CFAlert.Type=='error' %>text-danger<% end_if %>" style="line-height:0.6em"><i
                            style="line-height:0.6em; display:inline-block;" class="<% if $CFAlert.Type=='success' || $CFAlert.Type=='good' %>font-icon-check-mark-circle<% else_if $CFAlert.Type=='bad' || $CFAlert.Type=='error' %>font-icon-attention<% else %>font-icon-info-circled<% end_if %>"></i></div>
                        <div class="w-75">
                            $CFAlert.Message.RAW
                        </div>
                    </div>$DestroyCFAlert<% end_if %>
                    <% if $isReady %>
                        <% if $HasPermission('CF_PURGE_ALL') || $HasPermission('CF_PURGE_STYLESHEETS') || $HasPermission('CF_PURGE_JAVASCRIPT') || $HasPermission('CF_PURGE_IMAGES') %>
                        <div class="cloudflare-panel">
                            <div class="cloudflare-panel-title"><%t CloudFlare.QuickActionsLabel "Quick Actions" %></div>
                            <div class="cloudflare-panel-actions">
                                <% if $HasPermission('CF_PURGE_ALL') %><a href="{$Link('purge-all')}" class="btn btn-outline-primary"><%t CloudFlare.PurgeAllButton "Purge All" %></a><% end_if %>
                                <% if $HasPermission('CF_PURGE_STYLESHEETS') %><a href="{$Link('purge-stylesheets')}" class="btn btn-outline-primary"><%t CloudFlare.PurgeStylesheetsButton "Purge Stylesheets" %></a><% end_if %>
                                <% if $HasPermission('CF_PURGE_JAVASCRIPT') %><a href="{$Link('purge-javascript')}" class="btn btn-outline-primary"><%t CloudFlare.PurgeJavascriptButton "Purge Javascript" %></a><% end_if %>
                                <% if $HasPermission('CF_PURGE_IMAGES') %><a href="{$Link('purge-images')}" class="btn btn-outline-primary"><%t CloudFlare.PurgeImagesButton "Purge Images" %></a><% end_if %>
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

                    <div id="" class="cloudflare-message message warning d-flex align-items-center">
                        <div class="display-1 pr-4 text-warning" style="line-height:0.6em"><i style="line-height:0.6em; display:inline-block;" class="font-icon-attention"></i></div>
                        <div class="w-75">
                            <%t CloudFlare.NoCredentialsDefined "No Cloudflare credentials have been defined. Please see above, or refer to the readme to find out more." %>
                        </div>
                    </div>

                <% end_if %>

            <% else %>
                <div id="" class="cloudflare-message message bad error d-flex align-items-center">
                    <div class="display-1 pr-4 text-danger" style="line-height:0.6em"><i style="line-height:0.6em; display:inline-block;" class="font-icon-attention"></i></div>
                    <div class="w-75">
                        <%t CloudFlare.NoModulePermissions "You don't have permission to use this module." %>
                    </div>
                </div>
            <% end_if %>

            </div>

        </div>

        <div class="toolbar--south cms-content-actions cms-content-controls south" style="text-align:right; font-size:0.9em;">
            <%t CloudFlare.ModuleFooterLabel "Cloudflare Module" %> - <a href="https://www.steadlane.com.au/" target="_blank">Stead Lane</a> - BSD (3-Clause)<% if $CredentialsDefined %> | <%t CloudFlare.ZoneID "Zone ID" %>: $ZoneId <% end_if %>
        </div>

    </div>
</div>
