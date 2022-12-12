<?php

try {
    if (hasChatAccess()) {
?>
    <div id="chat-wrapper">
        <div class="fullpage-messenger" style="display:none">
            <iframe id="chat-frame" src="<?php echo LANDSPOT_ROOT ?>/live-chat" width="100%" height="100%" frameborder="0"></iframe>
        </div>
    </div>

<script type="text/javascript">
    var firstToggle = true;
    function toggleClick() {
        var chat = $("#chat-frame");

        if (firstToggle) {
            firstToggle = false;
            chat.attr('src', 'about:blank');

            setTimeout(function(){
                chat.attr('src', '<?php echo LANDSPOT_ROOT . "/live-chat" ?>');
            },  10);
        }

        $(".fullpage-messenger").toggle();
    }

    $(document).ready(function() {
        window.addEventListener('message', function(event){
            if (event && event.data && event.data.hasOwnProperty("unreadMessages")) {
                var messageCount = parseInt(event.data.unreadMessages);
                var counter = $(".counter");

                counter.html(messageCount+"");
                if (messageCount>0) {
                    counter.addClass("pulse");
                }   else {
                    counter.removeClass("pulse");
                }
            }
        });
    });
    // add listeners for window events
</script>

<?php
    }
}   catch (Exception $exception) {}