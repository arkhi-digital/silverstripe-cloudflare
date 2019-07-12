
<div id="pages-controller-cms-content" class="has-panel cms-content flexbox-area-grow fill-width fill-height $BaseCSSClasses" data-layout-type="border" data-pjax-fragment="Content" data-ignore-tab-state="true">
    $Tools

    <div class="fill-height flexbox-area-grow">
        <div class="cms-content-header north">
            <div class="cms-content-header-info flexbox-area-grow vertical-align-items fill-width">
                <a href="$BreadcrumbsBackLink" class="btn btn-secondary btn--no-text font-icon-left-open-big hidden-lg-up toolbar__back-button"></a>
                <% include SilverStripe\\Admin\\CMSBreadcrumbs %>
            </div>

            <div class="cms-content-header-tabs cms-tabset">
                <%--
                    uncomment to use tabs!
                <ul class="cms-tabset-nav-primary nav nav-tabs">
                    <li class="nav-item content-treeview<% if $TabIdentifier == 'edit' %> ui-tabs-active<% end_if %>">
                        <a href="$LinkPageEdit" class="nav-link cms-panel-link" title="Form_EditForm" data-href="$LinkPageEdit">
                            <%t SilverStripe\\CMS\\Controllers\\CMSMain.TabContent 'Content' %>
                        </a>
                    </li>
                    <li class="nav-item content-listview<% if $TabIdentifier == 'settings' %> ui-tabs-active<% end_if %>">
                        <a href="$LinkPageSettings" class="nav-link cms-panel-link" title="Form_EditForm" data-href="$LinkPageSettings">
                            <%t SilverStripe\\CMS\\Controllers\\CMSMain.TabSettings 'Settings' %>
                        </a>
                    </li>
                    <li class="nav-item content-listview<% if $TabIdentifier == 'history' %> ui-tabs-active<% end_if %>">
                        <a href="$LinkPageHistory" class="nav-link cms-panel-link" title="Form_EditForm" data-href="$LinkPageHistory">
                            <%t SilverStripe\\CMS\\Controllers\\CMSMain.TabHistory 'History' %>
                        </a>
                    </li>
                </ul>
                --%>
            </div>
        </div>

        <%--
            help!
        <div class="fill-width campaign-info cloudflare-help" style="<% if $ShowHelp %>display:none;<% end_if %>">
            <div class="campaign-info__icon"><span class="font-icon-white-question icon btn--icon-xl"></span></div>
            <div class="flexbox-area-grow campaign-info__content">
                <h3>How do campaigns work?</h3>
                <p>Campaigns allow multiple users to publish large amounts of content (pages, files, etc.) all at once from one place.</p>
                <div class="campaign-info__links"></div>
                <div class="campaign-info__content-buttons"></div>
            </div>
            <div class="campaign-info__banner-image"></div>
            <div class="campaign-info__buttons"><a class="btn campaign-info__close btn--no-text font-icon-cancel btn--icon-xl" role="button" tabindex="0"></a></div>
        </div>
        --%>

        <% if not $CredentialsDefined && $HasAnyAccess %>
        <div class="fill-width campaign-info cloudflare-help" style="<% if $ShowHelp %>display:none;<% end_if %>">
            <div class="campaign-info__icon"><span class="font-icon-white-question icon btn--icon-xl"></span></div>
            <div class="flexbox-area-grow campaign-info__content">
                <h3>Cloudflare for SilverStripe Module</h3>
                <p>To connect your SilverStripe website with your Cloudflare account, add your Cloudflare credentials to the site's .env file:</p>
                <p>
                    <b>CLOUDFLARE_AUTH_EMAIL="your@cloudflarelogin.email"</b><br>
                    <b>CLOUDFLARE_AUTH_KEY="yourcloudflareauthkey"</b>\
                </p>
            </div>
            <div class="display-1"><i class="font-icon-upload"></i></div>
        </div>
        <% end_if %>


        <div class="flexbox-area-grow fill-height">

            <%--
                button to show the help thing
            <div style="position:absolute; right:5px; margin-top:5px;"><a role="button" tabindex="0" onclick=" jQuery('.cloudflare-help').toggle(); " class="btn btn-secondary font-icon-white-question btn--icon-xl"></a></div>
            --%>

            <div style="padding:2rem; clear:both;">

            <% if $HasAnyAccess %>
                <% if $CredentialsDefined %>
                    <% if $CFAlert.Message %><div class="cloudflare-message message {$CFAlert.Type} d-flex align-items-center">
                        <div class="display-1 pr-4" style="line-height:0.6em"><i style="line-height:0.6em; display:inline-block;" class="font-icon-attention color-warning"></i></div>
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

                    <div id="" class="cloudflare-message message bad error d-flex align-items-center">
                        <div class="display-1 pr-4" style="line-height:0.6em"><i style="line-height:0.6em; display:inline-block;" class="font-icon-attention color-warning"></i></div>
                        <div class="w-75">
                            <%t CloudFlare.NoCredentialsDefined "No Cloudflare credentials have been defined. Please see the readme to find out more." %>
                        </div>
                    </div>

                <% end_if %>

            <% else %>
                <div id="" class="cloudflare-message message bad error d-flex align-items-center">
                    <div class="display-1 pr-4" style="line-height:0.6em"><i style="line-height:0.6em; display:inline-block;" class="font-icon-attention color-warning"></i></div>
                    <div class="w-75">
                        <%t CloudFlare.NoModulePermissions "You don't have permission to use this module." %>
                    </div>
                </div>
            <% end_if %>

            </div>

        </div>

        <div class="toolbar--south cms-content-actions cms-content-controls south" style="text-align:right; font-size:0.9em;">
            <%t CloudFlare.ModuleFooterLabel "Cloudflare Module" %> - <a href="//www.steadlane.com.au" target="_blank">Stead Lane</a> - BSD (3-Clause)<% if $CredentialsDefined %> | <%t CloudFlare.ZoneID "Zone ID" %>: $ZoneId <% end_if %>
        </div>

    </div>
</div>
