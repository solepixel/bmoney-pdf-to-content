jQuery(function($){
	$('.thumbnail_generator .conditional:not(.enabled)').hide();
	$('.thumbnail_generator input[type="radio"]').click(function(){
		$('.thumbnail_generator .conditional').hide();
		$(this).siblings('.conditional').show();
	});
});