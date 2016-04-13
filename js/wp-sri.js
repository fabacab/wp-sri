/**
 * Created by mark on 4/12/16.
 */
jQuery(function ($) {

    var checkboxes = $('.sri-exclude'),
        checkbox,
        boxId,
        data,
        hashVal,
        loading,
        nonce = options.security;

    checkboxes.on('change', function (e) {
        loading = $(e.target).next('span');
        loading.show();

        checkbox = $(e.target);
        hashVal = $(e.target.parentElement.parentElement.lastChild).html();
        boxId = checkbox.attr('id');

        console.log(checkbox.is(':checked'));
        console.log(boxId);
        checked = checkbox.is(':checked') ? true : false;

        data = {
            action: 'update_sri_exclude',
            hash: hashVal,
            url: boxId,
            checked: checked,
            security: nonce
        };
        jQuery.post(ajaxurl, data, function( resp ) {

            console.log( resp.data );
            loading.hide();
        });


    });

});