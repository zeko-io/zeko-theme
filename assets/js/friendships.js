jQuery(document).ready(function($) {
    // Friendship action buttons
    $(document).on('click', '.zeko-add-friend-btn', function() {
        var $button = $(this);
        var $container = $button.closest('.zeko-friendship-actions');
        var userId = $container.data('user-id');

        $button.prop('disabled', true).addClass('zeko-loading').text(zekoFriendshipData.i18n.saving || 'Processing...');

        $.ajax({
            url: zekoFriendshipData.ajax_url,
            type: 'POST',
            data: {
                action: 'zeko_friendship_action',
                nonce: zekoFriendshipData.nonce,
                user_id: userId,
                action_type: 'add'
            },
            success: function(response) {
                if (response.success) {
                    $container.html('<button class="btn btn-secondary" disabled>' + escapeHtml(zekoFriendshipData.i18n.request_sent) + '</button>');
                    showMessage(response.message, 'success');
                } else {
                    $button.removeClass('zeko-loading').prop('disabled', false).text('Add Friend');
                    showMessage(zekoFriendshipData.i18n.error + response.message, 'error');
                }
            },
            error: function() {
                $button.removeClass('zeko-loading').prop('disabled', false).text('Add Friend');
                showMessage(zekoFriendshipData.i18n.error + 'An error occurred.', 'error');
            }
        });
    });

    // Accept friend request
    $(document).on('click', '.zeko-accept-friend-btn', function() {
        var $button = $(this);
        var $container = $button.closest('.zeko-request-actions');
        var initiatorId = $container.data('initiator-id');

        $button.prop('disabled', true).addClass('zeko-loading').text(zekoFriendshipData.i18n.saving || 'Processing...');

        $.ajax({
            url: zekoFriendshipData.ajax_url,
            type: 'POST',
            data: {
                action: 'zeko_friendship_action',
                nonce: zekoFriendshipData.nonce,
                user_id: initiatorId,
                action_type: 'accept'
            },
            success: function(response) {
                if (response.success) {
                    $container.closest('.zeko-request-item').fadeOut(300, function() {
                        $(this).remove();
                        updateFriendCount(1);
                    });
                    showMessage(response.message, 'success');
                } else {
                    $button.removeClass('zeko-loading').prop('disabled', false).text('Accept');
                    showMessage(zekoFriendshipData.i18n.error + response.message, 'error');
                }
            },
            error: function() {
                $button.removeClass('zeko-loading').prop('disabled', false).text('Accept');
                showMessage(zekoFriendshipData.i18n.error + 'An error occurred.', 'error');
            }
        });
    });

    // Reject friend request
    $(document).on('click', '.zeko-reject-friend-btn', function() {
        var $button = $(this);
        var $container = $button.closest('.zeko-request-actions');
        var initiatorId = $container.data('initiator-id');

        $button.prop('disabled', true).addClass('zeko-loading').text(zekoFriendshipData.i18n.saving || 'Processing...');

        $.ajax({
            url: zekoFriendshipData.ajax_url,
            type: 'POST',
            data: {
                action: 'zeko_friendship_action',
                nonce: zekoFriendshipData.nonce,
                user_id: initiatorId,
                action_type: 'reject'
            },
            success: function(response) {
                if (response.success) {
                    $container.closest('.zeko-request-item').fadeOut(300, function() {
                        $(this).remove();
                    });
                    showMessage(response.message, 'success');
                } else {
                    $button.removeClass('zeko-loading').prop('disabled', false).text('Reject');
                    showMessage(zekoFriendshipData.i18n.error + response.message, 'error');
                }
            },
            error: function() {
                $button.removeClass('zeko-loading').prop('disabled', false).text('Reject');
                showMessage(zekoFriendshipData.i18n.error + 'An error occurred.', 'error');
            }
        });
    });

    // Remove friend
    $(document).on('click', '.zeko-remove-friend-btn', function() {
        var $button = $(this);
        var $container = $button.closest('.zeko-friendship-actions');
        var userId = $container.data('user-id');

        if (confirm(zekoFriendshipData.i18n.confirm_remove)) {
            $button.prop('disabled', true).addClass('zeko-loading').text(zekoFriendshipData.i18n.saving || 'Processing...');

            $.ajax({
                url: zekoFriendshipData.ajax_url,
                type: 'POST',
                data: {
                    action: 'zeko_friendship_action',
                    nonce: zekoFriendshipData.nonce,
                    user_id: userId,
                    action_type: 'remove'
                },
                success: function(response) {
                    if (response.success) {
                        $container.html('<button class="btn btn-primary zeko-add-friend-btn">Add Friend</button>');
                        updateFriendCount(-1);
                        showMessage(response.message, 'success');
                    } else {
                        $button.removeClass('zeko-loading').prop('disabled', false).text('Remove Friend');
                        showMessage(zekoFriendshipData.i18n.error + response.message, 'error');
                    }
                },
                error: function() {
                    $button.removeClass('zeko-loading').prop('disabled', false).text('Remove Friend');
                    showMessage(zekoFriendshipData.i18n.error + 'An error occurred.', 'error');
                }
            });
        }
    });

    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    function showMessage(message, type) {
        var $message = $('<div class="zeko-' + type + '-message"></div>');
        $message.text(message);
        $('.zeko-dashboard').prepend($message);
        setTimeout(function() {
            $message.fadeOut(300, function() {
                $(this).remove();
            });
        }, 3000);
    }

    function updateFriendCount(change) {
        var $friendCount = $('.zeko-stat-item .zeko-stat-label:contains("Friends")').siblings('.zeko-stat-value');
        if ($friendCount.length) {
            var currentCount = parseInt($friendCount.text()) || 0;
            $friendCount.text(currentCount + change);
        }
    }
});
