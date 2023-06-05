jQuery(document).ready(function($){

  if($('#affwp_dashboard_overview').length){
    $.ajax({
      type: 'GET',
      data: {
        action: 'affwp_dashboard_overview',
      },
      url: ajaxurl,
      success: function(response){
        $('#affwp_dashboard_overview .inside').html(response);
      }
    });
  }
});