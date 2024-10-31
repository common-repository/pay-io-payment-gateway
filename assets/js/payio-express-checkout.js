(function ($) {
  "use strict";
  $(document).on("click", ".payio-express-button", function (e) {
    e.preventDefault();
    $( "<div class='payio-loading'><div class='payio-loading-spinner'></div></div>" ).appendTo( ".woocommerce-page" );
    $.post({
      url: ajax_var.ajaxurl,
      data: {
        action: "ec_ajax_response",
        nonce: ajax_var.nonce,   // pass the nonce here
      },
      error: function (response) {
        $('.payio-loading').remove();
      },
      success: function (response) {
        if(response.success){
          window.location.href = response.data.gateway_url;
        }else {
         $('.payio-loading').remove();
        }
      },
    });
  });
})(jQuery);
