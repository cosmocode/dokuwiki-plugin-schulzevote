/**
 * @author Laurent Forthomme <lforthomme@protonmail.com>
 */

jQuery('select.plugin__schulzevote__vote_selector').change(function(){
    // start by setting everything to enabled
    jQuery(this).children('option').each(function() {
        jQuery(this).attr('disabled', false);
    });
    // loop each select and set the selected value to disabled in all other selects
    jQuery('select.plugin__schulzevote__vote_selector').each(function(){
        var $this = jQuery(this);
        jQuery('select.plugin__schulzevote__vote_selector').not($this).find('option').each(function(){
            if(jQuery(this).attr('value') == $this.val() && jQuery(this).attr('value') !== '-')
                jQuery(this).attr('disabled',true);
        });
    });
});
