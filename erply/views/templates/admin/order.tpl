<script type="text/javascript">
$( function() {
    
    Erply = {
        
        history: function(erply_oder_history) {
            if(erply_oder_history) {
                $('#erply-order-status').attr('class', 'icon-check');
            }
            
            if(erply_presta_order_history) {
                console.log('epoh: ', erply_presta_order_history);
                for (i = 0; i < erply_presta_order_history.length; i++) { 
                    var icon = 'icon-close';
                    elem = erply_presta_order_history[i];
                    console.log('elem: ', elem);
                    if(erply_oder_history && erply_oder_history.indexOf(elem.id_order_history) != -1) {
                        icon = 'icon-check';
                    }
                    
                    $('#erply-history').append('<li>' + elem.order_state_name + ' <i class="' + icon + '">' + '</li>');
                }
            }
        },
        
        sync: function() {
        	$.ajax({
        		url: erply_sync_url,
            	dataType:'json',
        		success: function(result) {
                    console.log('result: ', result[0]);
                    $('#erply-history').empty();
                    if(result[0].error) {
                        $('#erply-error').html('<p>' + result[0].errorMessage + '</p>');
                    } else {
                        erply_presta_order_history = result[0].erply_presta_order_history;
                        Erply.history(result[0].history);
                        $('#erply-order-status').attr('class', 'icon-check');
                    }
        		},
                error: function(jqXHR, textStatus, errorThrown) {
                    console.log('error: ' + errorThrown);
                }
        	});
        }
    };
    
    if(erply_erply_order_history) {
        Erply.history(erply_erply_order_history);
    }
    
});
</script>

<div class="row">
    <div class="col-lg-7">
        <div class="panel">
            <div class="panel-heading">
                <i class="icon-credit-card"></i>
                {l s='Erply Order Sync' mod='erply'}
            </div>
            
            <a class="btn btn-default" href="javascript:Erply.sync();">
                <i class="icon-refresh"></i>
                {l s='Sync Order' mod='erply'}
            </a>
            &nbsp;
            
            <span>
                {l s='Status' mod='erply'}: 
                <i id="erply-order-status" class="icon-remove-sign"></i>
            </span>
            
            <div class="well hidden-print">
                <ul id="erply-history">
                    
                </ul>
            </div>
            
            <div id="erply-error">
                
            </div>
        </div>
    </div>
</div>