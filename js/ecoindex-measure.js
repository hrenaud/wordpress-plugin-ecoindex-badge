jQuery(document).ready(function ($) {
  $('.ecoindex-measure-button').click(function (event) {
    event.preventDefault();
    console.log('clicked');
    var url = $(this).attr('data-page-url');
    var taskId = null;

    $.ajax({
      type: 'POST',
      url: ajaxurl,
      data: {
        action: 'ecoindex_measure',
        url: url,
      },
      success: function (response) {
        taskId = response;
        console.log('Task ID:', taskId);

        checkMeasureStatus();
      },
    });

    function checkMeasureStatus() {
      setTimeout(function () {
        $.ajax({
          type: 'GET',
          url: 'https://bff.ecoindex.fr/api/tasks/' + taskId,
          success: function (response) {
            console.log('Response:', response);
            if (response.status === 'done') {
              location.reload();
            } else {
              setTimeout(checkMeasureStatus, 15000);
            }
          },
          error: function (xhr, ajaxOptions, thrownError) {
            if (xhr.status === 422) {
              alert('Le nombre de requêtes pour ce domaine a été atteint.');
            } else {
              setTimeout(checkMeasureStatus, 15000);
            }
          },
        });
      }, 15000);
    }
  });
});
