jQuery(document).ready(function () {
    jQuery(document).on( 'click', '.bonipress-add-specific-hook', function() {
        var hook = jQuery(this).closest('.hook-instance').clone();
        hook.find('input.buycred-reward-creds').val('10');
        hook.find('input.buycred-reward-log').val('Belohnung für den Kauf von %plural%.');
        hook.find('select.buycred-reward-min').val('1');
        hook.find('input.buycred-reward-max').val('10');
        jQuery(this).closest('.widget-content').append( hook );
    }); 
    jQuery(document).on( 'click', '.bonipress-remove-specific-hook', function() {
        var container = jQuery(this).closest('.widget-content');
        if ( container.find('.hook-instance').length > 1 ) {
            var dialog = confirm("Möchtest Du diesen Hook wirklich entfernen?");
            if (dialog == true) {
                jQuery(this).closest('.hook-instance').remove();
            } 
        }
    }); 
}); 