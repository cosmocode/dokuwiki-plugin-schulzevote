/**
 * @author Laurent Forthomme <lforthomme@protonmail.com>
 */

jQuery('select[name*="vote"').change(function(){
    // start by setting everything to enabled
    jQuery('select[name*="vote"] option').attr('disabled',false);
    // loop each select and set the selected value to disabled in all other selects
    jQuery('select[name*="vote"]').each(function(){
        var $this = jQuery(this);
        jQuery('select[name*="vote"]').not($this).find('option').each(function(){
            if(jQuery(this).attr('value') == $this.val() && jQuery(this).attr('value') !== '-')
                jQuery(this).attr('disabled',true);
        });
    });
});
