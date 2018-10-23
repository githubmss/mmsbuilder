require(['jquery', 'jquery/ui'], function ($) {
    $(document).ready(function () {
        var tileValue = $('.tile_type').val();
        $(document).on('change', '.tile_type', function () {
            var tileValue = $('.tile_type').val();
            onChangeSelectBox(tileValue);
        });
        onChangeSelectBox(tileValue);
    });

    function onChangeSelectBox(tileValue)
    {
        if (!tileValue) {
            $('.banner_type').parent().parent().hide();
            $('#mssslider_banner_name').parent().parent().hide();
            $('.promotion_display').parent().parent().hide();
            $('.promotion_display_id').parent().parent().hide();
            $('.category_display_id').parent().parent().hide();
            $('.category_display').parent().parent().hide();
        } else if (tileValue == 1) {
            $('#mssslider_banner_name').parent().parent().hide();
            $('.banner_type').parent().parent().hide();
            $('.promotion_display').parent().parent().hide();
            $('.promotion_display_id').parent().parent().hide();
            $('.category_display_id').parent().parent().show();
            $('.category_display').parent().parent().show();
        } else if (tileValue == 2) {
            $('#mssslider_banner_name').parent().parent().show();
            $('.banner_type').parent().parent().show();
            $('.promotion_display').parent().parent().hide();
            $('.promotion_display_id').parent().parent().hide();
            $('.category_display_id').parent().parent().show();
            $('.category_display').parent().parent().hide();
        } else {
            $('#mssslider_banner_name').parent().parent().hide();
            $('.banner_type').parent().parent().hide();
            $('.promotion_display').parent().parent().show();
            $('.promotion_display_id').parent().parent().show();
            $('.category_display_id').parent().parent().hide();
            $('.category_display').parent().parent().hide();
        }
    }
});