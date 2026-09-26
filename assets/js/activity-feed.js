jQuery(document).ready(function($) {
    var activityFeed = $('.zeko-activity-feed');
    var loadMoreBtn = $('.zeko-load-more-activities');
    var loadingMessage = $('.zeko-loading-activities');
    var offset = 0;
    var perPage = parseInt(activityFeed.data('per-page'));
    var userId = parseInt(activityFeed.data('user-id'));
    var context = activityFeed.data('context');

    // Filter and search elements
    var activityTypeFilter = $('.zeko-activity-type-filter');
    var dateRangeFilter = $('.zeko-date-range-filter');
    var activitySearch = $('.zeko-activity-search');
    var activitySearchBtn = $('.zeko-activity-search-btn');
    var clearFiltersBtn = $('.zeko-clear-filters-btn');

    // Current filters
    var currentFilters = {
        activity_type: 'all',
        date_range: 'all',
        search_term: ''
    };

    function loadActivities(resetOffset) {
        if (resetOffset) {
            offset = 0;
            activityFeed.html('<p class="zeko-loading-activities">' + zekoActivityData.i18n.loading + '</p>');
        } else {
            loadingMessage.show();
        }
        loadMoreBtn.hide().prop('disabled', true);

        // Build filter data
        var filterData = {
            activity_type: currentFilters.activity_type,
            date_range: currentFilters.date_range,
            search_term: currentFilters.search_term
        };

        $.ajax({
            url: zekoActivityData.ajax_url,
            type: 'POST',
            data: {
                action: 'zeko_load_activity_feed',
                nonce: zekoActivityData.nonce,
                per_page: perPage,
                offset: offset,
                user_id: userId,
                context: context,
                filters: filterData
            },
            success: function(response) {
                loadingMessage.hide();
                if (response.success) {
                    if (response.data.html) {
                        if (resetOffset) {
                            activityFeed.html(response.data.html);
                        } else {
                            activityFeed.append(response.data.html);
                        }
                        offset += perPage;
                        if (response.data.has_more) {
                            loadMoreBtn.show().prop('disabled', false);
                        } else {
                            loadMoreBtn.hide();
                            if (offset === perPage) { // Only show "no more" if we're at the beginning
                                activityFeed.append('<p class="zeko-no-activities-found">' + zekoActivityData.i18n.no_more + '</p>');
                            }
                        }
                    } else if (offset === 0) {
                        activityFeed.html('<p class="zeko-no-activities-found">' + zekoActivityData.i18n.no_more + '</p>');
                    } else {
                        loadMoreBtn.hide();
                        activityFeed.append('<p class="zeko-no-activities-found">' + zekoActivityData.i18n.no_more + '</p>');
                    }
                 } else {
                    var errorMessage = response.data && response.data.message ? response.data.message : 'Unknown error';
                    activityFeed.append('<p class="zeko-error-loading-activities">' + zekoActivityData.i18n.error + errorMessage + '</p>');
                }
            },
            error: function() {
                loadingMessage.hide();
                activityFeed.append('<p class="zeko-error-loading-activities">' + zekoActivityData.i18n.error + '</p>');
            }
        });
    }

    // Filter change handlers
    activityTypeFilter.on('change', function() {
        currentFilters.activity_type = $(this).val();
        loadActivities(true);
    });

    dateRangeFilter.on('change', function() {
        currentFilters.date_range = $(this).val();
        loadActivities(true);
    });

    activitySearchBtn.on('click', function() {
        currentFilters.search_term = activitySearch.val();
        loadActivities(true);
    });

    activitySearch.on('keypress', function(e) {
        if (e.which === 13) { // Enter key
            currentFilters.search_term = $(this).val();
            loadActivities(true);
        }
    });

    clearFiltersBtn.on('click', function() {
        activityTypeFilter.val('all');
        dateRangeFilter.val('all');
        activitySearch.val('');
        currentFilters = {
            activity_type: 'all',
            date_range: 'all',
            search_term: ''
        };
        loadActivities(true);
    });

    // Initial load
    if (activityFeed.length) {
        loadActivities(true);
    }

    // Load more button click
    loadMoreBtn.on('click', function() {
        loadActivities(false);
    });
});
