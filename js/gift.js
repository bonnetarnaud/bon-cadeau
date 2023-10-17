


jQuery( document ).ready(function() {
    console.log( "ready!" );

jQuery('#callajaxregeneratedgiftcard').on('click', function(e){
    e.preventDefault()
    let order_id = jQuery(this).data('order');
    regenerated_gift_card(order_id);
   
});

function regenerated_gift_card(order_id) {
    jQuery('#regeneratedgiftcardspinner').show();
    jQuery('#callajaxregeneratedgiftcard').hide();
    jQuery.ajax({
        url: script_ajax_object.ajaxurl,
        type: 'POST',
        data: {
            'action':'regenerated_gift_card',
            orderid : order_id
        },
        success: function( response ) {
        	console.log(response);
            jQuery('#regeneratedgiftcardspinner').hide();
            jQuery('#callajaxregeneratedgiftcard').show();
            document.location.reload();
        },
        error : function( data ) { // en cas d'échec
            // Sinon je traite l'erreur
            console.log( 'Erreur…', data );
        }
    });
}
});