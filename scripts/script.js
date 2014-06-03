function refresh_all(){	
    $("input:submit, button, .button").not('.ui-button').button();
	$("#menu_buttons",".optionbuttons").buttonset();  
    $(".accordion").accordion({ collapsible: true, active: false });
    $(".carousel").not('.ui-carousel').rcarousel({width: 200, height: 200});
    $( "#ui-carousel-next" )
    	.add( "#ui-carousel-prev" )
    	.hover(
    		function() {
    			$( this ).css( "opacity", 0.7 );
    		},
    		function() {
    			$( this ).css( "opacity", 1.0 );
    		}
    	);
    $(".popular_wrap",".carousel").hover(function(){$(".popular",this).addClass("popular-highlight")},function(){$(".popular",this).removeClass("popular-highlight")});
}

function update_price(){
    var price = parseFloat($('#price input').val());
    $('.dd-container:visible').each(function(){
        var hidden_price = $(this).attr("id") + "_choice_" + $(this).data('ddslick').selectedData.value;
        hidden_price = parseFloat($('#'+hidden_price).val());

        if(!isNaN(hidden_price)){
            price += hidden_price;       
        }
    });
    if(price > 0){
        $('#price span:first').html(price.toFixed(2));        
    }
}

function get_options(){
    var returnme = "";
    $('.dd-container:visible').each(function(){
        returnme += returnme == "" ? "" : "||";
        returnme += $(this).attr("id") + "::" + $(this).data('ddslick').selectedData.value;
    });    
    return returnme;
}

var randomNum = Math.floor(Math.random() * 8) - 4;
$('body').animate({"background-size": "+="+randomNum+"%"}, 800);

refresh_all();