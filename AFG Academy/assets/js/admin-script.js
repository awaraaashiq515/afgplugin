// AFG Academy Admin Scripts

jQuery(document).ready(function($) {
    // Common admin functionality
    
    // Search trainee AJAX
    $('.dkm-search-trainee').on('keyup', function() {
        var query = $(this).val();
        if (query.length > 2) {
            // Implement search logic
        }
    });
    
    // Confirm actions
    $('.dkm-confirm').on('click', function(e) {
        if (!confirm('Are you sure?')) {
            e.preventDefault();
        }
    });
});