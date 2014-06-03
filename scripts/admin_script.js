function refresh_all(){	
    $("input:submit, button, .button").not('.ui-button').button();
	$("#menu_buttons",".optionbuttons").buttonset();  
    $(".accordion").accordion({ collapsible: true, active: false });
    $('.edit_creation').editable('./ajax/ajax.php', { 
        submitdata : function (value, settings) {
            return {
                action: "save_creation_edit",
                creationid: $(this).attr("rel")
            };
        },
        onblur : "submit",
        callback : function(value, settings) {
            console.log(this);
            console.log(value);
            console.log(settings);
        }
    });
}

var randomNum = Math.floor(Math.random() * 8) - 4;
$('body').animate({"background-size": "+="+randomNum+"%"}, 800);

refresh_all();