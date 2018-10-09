jQuery( document ).ready( function( $ ) {
    
    $( mt2mbaPointer.pointers ).each( function( i ) {
        mt2mba_open_pointer( i );
    } );

    function mt2mba_open_pointer( i ) {
        pointer = mt2mbaPointer.pointers[ i ];
        options = $.extend( pointer.options, {
            close: function( ) {
                $.post( ajaxurl, {
                    pointer: pointer.pointer_id,
                    action: 'dismiss-wp-pointer'
                } );
            }
        } );
 
        $(pointer.target).pointer( options ).pointer( 'open' );
    }
} );