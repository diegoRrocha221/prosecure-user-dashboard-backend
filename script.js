$.getScript('https://code.jquery.com/ui/1.12.1/jquery-ui.min.js', function() {
  $(document).ready(function() {
    $('#showBurger').click(function(event) {
      $('.mobile-nav').toggle('slide', { direction: 'right' }, 300);
      event.stopPropagation();
    });

    $(document).click(function(event) {
      var mobileNav = $('.mobile-nav');
      var showBurger = $('#showBurger');

      if (!mobileNav.is(event.target) && !showBurger.is(event.target) && mobileNav.is(':visible')) {
        mobileNav.hide('slide', { direction: 'right' }, 300);
      }
    });
  });
});
