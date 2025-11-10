    $(document).ready(function () {
        $('.delete-button').on('click', function (e) {
            let todoId = $(this).closest('li').attr('id');
            $.ajax({
                url: '../../router/router.php',
                type: 'POST',
                data: {
                    delete_todo: true,
                    id: todoId
                },
                success: function (response) {
                    location.reload();
                },
                error: function (xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                }
            });
        });

        $('.task-checkbox').on('click', function (e) {
            let todoId = $(this).closest('li').attr('id');
            let isDone = $(this).is('[name=mark_as_done]');
            let action = isDone ? 'mark_as_done' : 'mark_as_undone';

            let data = {};
            data[action] = true;
            data['id'] = todoId;

            $.ajax({
                url: '../../router/router.php',
                type: 'POST',
                data: data,
                success: function (response) {
                    location.reload();
                },
                error: function (xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                }
            });
        });

        $('#signout').on('click', function (e) {
            $.ajax({
                url: '../../router/router.php',
                type: 'POST',
                data: {
                    "logout_user": true
                },
                success: function (response) {
                    location.reload();
                },
                error: function (xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                }
            })
        })

        // Section filter logic
        $('#section-selector').on('click', '.selector-button', function() {
            const sectionToShow = $(this).attr('data-section');

            // Update active button style
            $('.selector-button').removeClass('active-section-button');
            $(this).addClass('active-section-button');

            if (sectionToShow === 'All') {
                $('.section-container').show();
            } else {
                $('.section-container').hide();
                $('.section-container').filter(function() {
                    return $(this).attr('data-section') === sectionToShow;
                }).show();
            }
        });
    });