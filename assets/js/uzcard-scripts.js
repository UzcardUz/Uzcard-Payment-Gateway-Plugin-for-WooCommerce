
jQuery(document).ready(function($){


    $('body').on('updated_checkout', function() {

		usingGateway();
        jQuery('input[name="payment_method"]').change(function(){
              usingGateway();
        });

    });


	function usingGateway(){

		if(jQuery('form[name="checkout"] input[name="payment_method"]:checked').val() == 'uzcard'){
	    	$('#place_order').hide();
	    }else{
	         $('#place_order').show();
	    }

	}


	var validation = {

	    isPhoneNumber:function(str) {
			var pattern = /^\d{12}$/;
			return pattern.test(str);
	    },
	    isExpireDate:function (str) {
			var pattern = /^\d{4}$/;
			return pattern.test(str); 
	    },
	    isCardNumber:function(str) {
			var pattern = /^\d{6}$/;
			return pattern.test(str);
	    }

	}; 



   $("form").on("keyup", ".uzcard-payment-field", function(e) {

        Phonenumber = document.getElementById('uzcard-phone-number').value;
	  	cartExpire = document.getElementById('uzcard-card-expiry-uzcard').value;
        CardLastNum = document.getElementById('uzcard-last_numbers_card').value;

        var month = cartExpire.substr(0,2);
        var year = cartExpire.substr(3,4);
        cartExpire = year +''+month;

        var two_number = CardLastNum.substr(0, 2);
        var four_number = CardLastNum.substr(3, 6);
       	CardLastNum = two_number +''+four_number;

	  	if(validation.isPhoneNumber(Phonenumber) && validation.isCardNumber(CardLastNum) && validation.isExpireDate(cartExpire)){
			$('.uzcard-alert-error').remove(); //remove error block

			var data = {
				cardLastNum: CardLastNum,
				phonenumber: Phonenumber,
				expire: cartExpire,
				summa: cartTotal,
				eposId: eposId,
				key: key
			};

			$.ajax({
				url: 'http://195.158.28.125:9099/api/payment/PaymentsWithOutRegistration',
				type: 'post',
				dataType: 'json',
				contentType: 'application/json',
				data: JSON.stringify(data),
				success: function( msg, status, xhr ) {
					if (msg.result) {
						msg = msg.result;
						$('#place_order').prop("disabled", false);
						$("#uniqueInput").val(msg.uniques);
						$("#form-first").fadeOut();
						$("#form-second").fadeIn();
						$('#place_order').show();
					} else if(msg.error) {
						msg = msg.error.message;
						// var msg = "Повторите позже";
						$('.wc-payment-form').prepend('<div class="alert alert-danger uzcard-alert-error">'+msg+'</div>');
					} else {
						$('.wc-payment-form').prepend('<div class="alert alert-danger uzcard-alert-error">dfsdf</div>');
					}
			       
				}


			});


	  	} else {

	  	}

   });

	var error = false;
    $('body').on('keyup', '.wc-credit-card-form-card-expiry-uzcard', function(e){
        $('.expiredDateErrorClass').css('display', 'none');
        var value = $(this).val();

        if(error){
        	error = false;
        	$(this).val('');
		}

    	if(value.length == 2 && e.keyCode !=8){

    		if(value * 1 > 12 || value * 1 == 0){
    			$('.expiredDateErrorClass').css('display', 'block');
    			error = true;
    			return false;
			}

    		value = value + '/';
    		$(this).val(value);
		}
    })

	$('body').on('keypress', '.wc-credit-card-form-last_numbers_card', function(e){
        var value = $(this).val();
        if(value.length == 2 && e.keyCode !=8){
        	value = value + '-';
            $(this).val(value);
        }

	})




});

function validateExpireDateNumbers(evt) {

    var theEvent = evt || window.event;

    // Handle paste
    if (theEvent.type === 'paste') {
        key = event.clipboardData.getData('text/plain');
    } else {
        // Handle key press
        var key = theEvent.keyCode || theEvent.which;
        key = String.fromCharCode(key);
    }
    var regex = /[0-9]|\./;
    if( !regex.test(key) ) {
        theEvent.returnValue = false;
        if(theEvent.preventDefault) theEvent.preventDefault();
    }
}







