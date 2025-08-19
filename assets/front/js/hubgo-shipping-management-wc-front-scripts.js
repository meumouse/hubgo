/**
 * Display loader and hide span on click
 */
jQuery( function($) {
  $('.button-loading').on('click', function() {
      let $btn = $(this);
      let originalText = $btn.text();
      let btnWidth = $btn.width();
      let btnHeight = $btn.height();

      // stay original width and height
      $btn.width(btnWidth);
      $btn.height(btnHeight);

      // Add spinner inside button
      $btn.html('<span class="spinner-border spinner-border-sm"></span>');
    
      setTimeout(function() {
        // Remove spinner
        $btn.html(originalText);
        
      }, 5000);
    });
});