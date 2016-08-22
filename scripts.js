var switch_db_dropin_refresh = false;
var switch_db_dropin_timeout = false;

jQuery("#wp-admin-bar-switch-db-dropin > .ab-item").on('click',function(ev) {
    ev.preventDefault();

    if (jQuery("#wp-admin-bar-switch-db-dropin").hasClass('switching'))
        return;

    if (ev.shiftKey)
        switch_db_dropin_refresh = true;

    var checked = jQuery("#wp-admin-bar-switch-db-dropin input[type='radio']:checked");

    if (checked.closest('li').next('li').length) {
        console.log('next');
        checked.closest('li').next('li').find('.ab-item').click();
    } else {
        console.log('first');
        jQuery("#wp-admin-bar-switch-db-dropin ul.ab-submenu input[type='radio']:not(:checked):first").closest('.ab-item').click();
    }
});

jQuery("#wp-admin-bar-switch-db-dropin .ab-submenu .ab-item").on('click',function(ev) {
    ev.preventDefault();

    if (jQuery(this).closest('li').find('input').is(':checked'))
        return;

    clearTimeout(switch_db_dropin_timeout);

    if (jQuery("#wp-admin-bar-switch-db-dropin").hasClass('switching'))
        return;

    jQuery("#wp-admin-bar-switch-db-dropin ul.ab-sub-secondary").remove();

    if (false === switch_db_dropin_refresh && ev.shiftKey)
        switch_db_dropin_refresh = true;

    var that = jQuery(this);
    var href = that.attr('href').replace('#','');

    jQuery("#wp-admin-bar-switch-db-dropin").addClass('switching');

    jQuery("#wp-admin-bar-switch-db-dropin .ab-sub-wrapper").addClass('keep-open');
    jQuery("#wp-admin-bar-switch-db-dropin ul.ab-submenu").after('<ul class="ab-sub-secondary ab-submenu"><li><a class="ab-item">Switching...</a></li></ul>');

    jQuery.post(
        switch_db_dropin.ajaxurl,
        {
            action: 'switch_db_dropin',
            plugin: href,
            nonce: switch_db_dropin.nonce
        },
        function(data) {
            jQuery("#wp-admin-bar-switch-db-dropin").removeClass('switching');

            if ('true' === data) {
                jQuery('#wp-admin-bar-switch-db-dropin .ab-submenu .ab-item input[checked="checked"]').removeAttr('checked');
                that.find('input').attr('checked','checked');
                jQuery("#wp-admin-bar-switch-db-dropin ul.ab-sub-secondary > li > .ab-item").html('Switched!');
                jQuery("#wp-admin-bar-switch-db-dropin > .ab-item > .ab-label").html(switch_db_dropin.supported_plugins[href]['abbr']);
                if (switch_db_dropin_refresh)
                    location.reload(true);
            } else
                jQuery("#wp-admin-bar-switch-db-dropin ul.ab-sub-secondary > li > .ab-item").html('Failed.');

            switch_db_dropin_timeout = setTimeout(function() {
                jQuery("#wp-admin-bar-switch-db-dropin .ab-sub-wrapper.keep-open").removeClass('keep-open');
                jQuery("#wp-admin-bar-switch-db-dropin ul.ab-sub-secondary").slideUp(400,function() {
                    jQuery("#wp-admin-bar-switch-db-dropin ul.ab-sub-secondary").remove();
                });
            },2000);
        }
    );
});
