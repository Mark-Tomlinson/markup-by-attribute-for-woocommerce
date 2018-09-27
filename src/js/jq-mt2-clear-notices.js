jQuery( document ).on( 'click', '.mt2mba_ver2_4_notice .notice-dismiss', function()
    {
        jQuery.ajax(
            {
                data:
                {
                    url: ajaxurl,
                    action: 'mt2mba_ver2_4_notice_clear'
                }
            }
        )
    }
);