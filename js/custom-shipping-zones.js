jQuery(document).ready(function($){

    // Function to populate regions
    function populate_regions(zone_id){
        var $regions = $('#csmfw_shipping_zone_regions');
        
        // Clear previous selection
        $regions.val(null).trigger('change');
        $regions.prop('disabled', true); // disable initially

        if(!zone_id || zone_id === '') return; // If default selected, keep disabled

        // Enable the regions field
        $regions.prop('disabled', false);

        // Ajax call to get regions
        $.post(csmfw_shipping.ajax_url, { 
            action: 'csmfw_get_zone_regions', 
            zone_id: zone_id 
        }, function(response){
            if(response.success){
                var options = '';
                $.each(response.data, function(i, region){
                    options += '<option value="'+region+'" selected>'+region+'</option>';
                });
                $regions.html(options).trigger('change');
            }
        });
    }

    // On change of zone dropdown
    $('#csmfw_shipping_zone_name').on('change', function(){
        var zone_id = $(this).val();
        populate_regions(zone_id);
    });

    // Trigger initially for default selected
    var initial_zone = $('#csmfw_shipping_zone_name').val();
    populate_regions(initial_zone);

    // Initialize select2 for multiselect
    $('#csmfw_shipping_zone_regions').select2({
        width: '40%',
        placeholder: 'Select regions'
    });

});
