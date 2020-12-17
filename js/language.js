(function($, _) {

  Drupal.behaviors.admin_language = {
    attach: function(context, settings) {
      $('[data-block-plugin-id=dyniva_admin_language_switcher] .form-select').on("click", function(e) {
        e.stopPropagation();
        e.preventDefault();
      }).on('change', function() {
        var site_url = $('.dropdown-menu').attr('data-site-url');
        location.href = site_url + '/manage/user/admin_language_switch/'+this.value+'?destination='+document.location.pathname;
      });
    }
  };
})(jQuery, _);
