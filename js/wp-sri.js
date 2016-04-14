/**
 * Created by mark on 4/12/16.
 */
jQuery(function ($) {

    var checkboxes = $('.sri-exclude'),
        checkbox,
        boxId,
        data,
        loading,
        nonce = options.security;

    // Progressive enhancement
    checkboxes.prop('disabled', false ).css('pointer-events', 'auto' );

    checkboxes.on('change', function (e) {
        loading = $(e.target).next('span');
        loading.show();

        checkbox = $(e.target);
        boxId = checkbox.attr('id');

        // console.log(checkbox.is(':checked'));
        // console.log(boxId);
        checked = checkbox.is(':checked') ? true : false;

        data = {
            action: 'update_sri_exclude',
            url: boxId,
            checked: checked,
            security: nonce
        };
        jQuery.post(ajaxurl, data, function( resp ) {

            // console.log( resp.data );
            loading.hide();
        });


    });

});