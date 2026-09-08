$(document).ready(function() {
    // 1. Function to fetch and update the total spent
    function updateTotal() {
        $.post('backend.php', { action: 'calculate' }, function(response) {
            if(response.status === 'success') {
                // Update the UI with the calculated total from calc.exe
                $('.total-spent').text('₹' + response.total);
            } else {
                console.error('Error calculating total:', response.message);
            }
        }, 'json');
    }

    // Load the total immediately when the page opens
    updateTotal();

    // 2. Handle the form submission to add a new expense
    $('form').on('submit', function(e) {
        e.preventDefault(); // Stop the page from refreshing

        // Gather data from the form inputs
        let expenseData = {
            action: 'add',
            name: $('#expenseName').val(),
            amount: $('#amount').val(),
            category: $('#category').val()
        };

        // Send data to PHP
        $.post('backend.php', expenseData, function(response) {
            if(response.status === 'success') {
                // Clear the form fields
                $('form')[0].reset();
                
                // Immediately recalculate and update the new total
                updateTotal();
            } else {
                alert('Error: ' + response.message);
            }
        }, 'json');
    });
});