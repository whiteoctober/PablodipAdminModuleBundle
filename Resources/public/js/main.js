$(function () {

    // Add a confirmation dialogue to any element with a
    // "data-are-you-sure" attribute equalling 1
    // For example, the delete button in ther Admin users list
    $('body').on('click', '[data-method]', {}, function (event) {
        event.preventDefault();

        var result = true
        if ($(this).attr("data-are-you-sure") == 1) {
            result = confirm("Are you sure?");
        }
        if (result) {
            $('<form>')
                .attr({method: 'POST', action: $(this).attr('href')})
                .hide()
                .append($('<input>').attr({type: 'hidden', name: '_method', value: $(this).attr('data-method')}))
                .appendTo('body')
                .submit()
            ;
        }
    });

    // Slide open/slide closed the Advanced search panel when
    // you click the Advanced search link
    $('#admin-list-advanced-search-switch').click(function () {
        $('#admin-list-advanced-search').slideToggle();

        return false;
    });

    // Slide closed the Advanced search panel when you click the Cancel button
    // within it
    $('#admin-list-advanced-search-cancel').click(function () {
        $('#admin-list-advanced-search').slideToggle();

        return false;
    });

    // Turn on/off all the tickboxes in the Admin users table if you turn
    // on/off the tickbox in that table's header
    $('.admin-list-th-checkbox input').click(function () {
        var inputs = $('.admin-list-table .admin-list-td-checkbox input');
        if ($(this).prop('checked')) {
            inputs.prop('checked', true);
        } else {
            inputs.prop('checked', false);
        }
    });

    // Controls for the Admin user list's bulk actions form
    $('#admin-batch-form').submit(function () {
        var elements = [];

        // If the "All" tickbox is not ticked, look for specific ticked input
        // fields in the Admin user list
        // (if it is ticked, the batch action will affect every row in the
        // table when the form is submitted)
        if (!$('#admin-batch-form input[name="all"]').prop('checked')) {
            $('.admin-list-td-checkbox input').each(function (index, value) {
                var element = $(value);
                if (element.prop('checked')) {
                    elements[elements.length] = element.attr('value');
                }
            });

            // Concatenate any values (IDs really) you've found from any ticked
            // tickboxes into a comma separated string
            // Assign that string as the value of the hidden "ids" field
            $('#admin-batch-form input[name="ids"]').attr('value', elements.join(','));
        }
    });
});
