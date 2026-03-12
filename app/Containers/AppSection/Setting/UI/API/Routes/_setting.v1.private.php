<?php

/**
 * @apiDefine GeneralSettingsResponse
 *
 * @apiSuccess {Object} data
 * @apiSuccess {String} data.type
 * @apiSuccess {String} data.id
 * @apiSuccess {String[]} data.admin_email
 * @apiSuccess {String} data.time_zone
 * @apiSuccess {Boolean} data.enable_send_error_reporting_via_email
 * @apiSuccess {String} data.locale_direction
 * @apiSuccess {String} data.locale
 * @apiSuccess {Boolean} data.redirect_404_to_homepage
 * @apiSuccess {Number} data.request_log_data_retention_period
 * @apiSuccess {Number} data.audit_log_data_retention_period
 */

/**
 * @apiDefine PhoneNumberSettingsResponse
 *
 * @apiSuccess {Object} data
 * @apiSuccess {String} data.type
 * @apiSuccess {String} data.id
 * @apiSuccess {Boolean} data.phone_number_enable_country_code
 * @apiSuccess {String[]} data.phone_number_available_countries
 * @apiSuccess {Number} data.phone_number_min_length
 * @apiSuccess {Number} data.phone_number_max_length
 */

/**
 * @apiDefine AdminAppearanceSettingsResponse
 *
 * @apiSuccess {Object} data
 * @apiSuccess {String} data.type
 * @apiSuccess {String} data.id
 * @apiSuccess {String} data.admin_logo
 * @apiSuccess {Number} data.admin_logo_max_height
 * @apiSuccess {String} data.admin_favicon
 * @apiSuccess {String} data.admin_favicon_type
 * @apiSuccess {String[]} data.login_screen_backgrounds
 * @apiSuccess {String} data.admin_title
 * @apiSuccess {String} data.admin_primary_font
 * @apiSuccess {String} data.admin_primary_color
 * @apiSuccess {String} data.admin_secondary_color
 * @apiSuccess {String} data.admin_heading_color
 * @apiSuccess {String} data.admin_text_color
 * @apiSuccess {String} data.admin_link_color
 * @apiSuccess {String} data.admin_link_hover_color
 * @apiSuccess {String} data.admin_appearance_locale
 * @apiSuccess {String} data.admin_appearance_locale_direction
 * @apiSuccess {String} data.rich_editor
 * @apiSuccess {Boolean} data.enable_page_visual_builder
 * @apiSuccess {String} data.admin_appearance_layout
 * @apiSuccess {String} data.admin_appearance_container_width
 * @apiSuccess {Boolean} data.admin_appearance_show_menu_item_icon
 * @apiSuccess {Boolean} data.show_admin_bar
 * @apiSuccess {Boolean} data.show_theme_guideline_link
 * @apiSuccess {String} data.admin_appearance_custom_css
 * @apiSuccess {String} data.admin_appearance_custom_header_js
 * @apiSuccess {String} data.admin_appearance_custom_body_js
 * @apiSuccess {String} data.admin_appearance_custom_footer_js
 */
