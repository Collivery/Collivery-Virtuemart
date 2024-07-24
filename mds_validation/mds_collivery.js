jQuery.noConflict();
jQuery(document).ready(function()
{
    // Fix for virtuemarts stupid validation
    jQuery('#userForm').each(function()
    {
        if (jQuery(this).attr('name') == 'userForm')
        {
            if(jQuery('#shipto_virtuemart_country_id').length > 0)
            {
                jQuery('.shipto_virtuemart_country_id').attr('for', 'shipto_virtuemart_country_id');
                jQuery('.shipto_virtuemart_state_id').attr('for', 'shipto_virtuemart_state_id');
                jQuery('.shipto_mds_suburb').attr('for', 'shipto_mds_suburb');
                
                jQuery('#shipto_virtuemart_country_id').addClass('required');
                jQuery('#shipto_virtuemart_state_id').addClass('required');
                jQuery('#shipto_mds_suburb').addClass('required');
            }
            else if(jQuery('#virtuemart_country_id').length > 0)
            {
                jQuery('.virtuemart_country_id').attr('for', 'virtuemart_country_id');
                jQuery('.virtuemart_state_id').attr('for', 'virtuemart_state_id');
                jQuery('.mds_suburb_id').attr('for', 'mds_suburb_id');

                jQuery('#virtuemart_country_id').addClass('required');
                jQuery('#virtuemart_state_id').addClass('required');
                jQuery('#mds_suburb_id').addClass('required');                
            }
        }                
    });
    
    // This is here to inject our validation into the page because of a bug in virtuemart
    jQuery('#userForm button').each(function()
    {
        if (jQuery(this).attr('type') == 'submit' && jQuery(this).attr('name') == 'userForm')
        {
                
            var old_onclick = jQuery(this).attr('onclick');
            jQuery(this).attr('onclick', "");
            jQuery(this).on("click", function(event)
            {
                event.preventDefault();
                
                if(document.formvalidator.isValid(userForm))
                {
                    userForm.submit();
                    return true;
                }
                else
                {
                    alert('Required field is missing');
                    return false;
                }
            });
        }
    });
    
    // --------------------------------------------------------------------

    jQuery('#shipto_virtuemart_state_id').change(function()
    {
        var text = jQuery("#shipto_virtuemart_state_id option:selected").text();
        var val = jQuery("#shipto_virtuemart_state_id option:selected").val();
        if (val != "")
        {
            jQuery.ajax(
                    {
                        url: base_url + "index.php?option=com_virtuemart&controller=mds&task=suburbs&town_name=" + text, success: function(result)
                        {
                            jQuery("#shipto_mds_suburb_id").removeAttr("style", "").removeClass("chzn-done").data("chosen", null).next().remove();
                            jQuery('#shipto_mds_suburb_id').html(result).chosen();
                        }
                    });
        }
        else
        {
            jQuery("#shipto_mds_suburb_id").removeAttr("style", "").removeClass("chzn-done").data("chosen", null).next().remove();
            jQuery('#shipto_mds_suburb_id').html('<option value="" selected="selected">-- Select --</option>').chosen();
        }
    });

    // --------------------------------------------------------------------

    jQuery('#virtuemart_state_id').change(function()
    {
        var text = jQuery("#virtuemart_state_id option:selected").text();
        var val = jQuery("#virtuemart_state_id option:selected").val();
        if (val != "")
        {
            jQuery.ajax(
                    {
                        url: base_url + "index.php?option=com_virtuemart&controller=mds&task=suburbs&town_name=" + text, success: function(result)
                        {
							jQuery("#mds_suburb_id").removeAttr("style", "").removeClass("chzn-done").data("chosen", null).next().remove();
                            jQuery('#mds_suburb_id').html(result).chosen();
							
                            jQuery("#shipto_mds_suburb_id").removeAttr("style", "").removeClass("chzn-done").data("chosen", null).next().remove();
                            jQuery('#shipto_mds_suburb_id').html(result).chosen();
						}
                    });
        }
        else
        {
            jQuery("#mds_suburb_id").removeAttr("style", "").removeClass("chzn-done").data("chosen", null).next().remove();
            jQuery('#mds_suburb_id').html('<option value="" selected="selected">-- Select --</option>').chosen();
        }
    });

    // --------------------------------------------------------------------
});