(function($) {
	
	var yolb_process = false;
	
	$('a.yolb-button').click(function(event) {
		event.preventDefault();
		
		if(yolb_process) return false;
		yolb_process = true;
		var button = $(this);
		var id = button.attr('data-id');
		var nonce = button.attr('data-nonce');
		var count = button.next('span.yolb-count');
		var count_value = count.html();
		
		count.html('..');
		
		$.post(yolb.url, {
			action: 'yolb_like',
			id: id,
			nonce: nonce
		}, function(response) {
			if(response.liked) button.html(yolb.unlike);
			else button.html(yolb.like);
			count.html(response.like);
		}).fail(function() {
			count.html(count_value);
		}).done(function() {
			yolb_process = false;
		});
		
	});
	
})(jQuery);