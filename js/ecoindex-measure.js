jQuery(document).ready(function ($) {
  var taskId; // déclare la variable taskId en dehors de la fonction success
  var button;
  $('.ecoindex-measure-button').click(function (event) {
    event.preventDefault();
    var url = $(this).attr('data-page-url');
    var width = 1920;
    var height = 1080;
    console.log('Looking for', url);
    button = $(this);
    button.prop('disabled', true);
    button.text('Mesure en cours...');

    $.ajax({
      type: 'POST',
      url: ecoindex_measure_params.ajaxurl,
      data: {
        action: 'ecoindex_measure',
        url: url,
        width: width,
        height: height,
        _ajax_nonce: ecoindex_measure_params.nonce,
      },
      success: function (response) {
        taskId = response;
        console.log('Task ID:', taskId);
        switch (taskId) {
          case 'You have reached the daily limit':
            alert(taskId);
            break;

          case 'Validation Error':
            alert(taskId);
            break;

          default:
            checkMeasureStatus(taskId);
            nb_try_mesure = 3;
            break;
        }
      },
      error: function (response) {
        console.warn(`response`, JSON.stringify(response));
        // Réactiver le bouton et changer son libellé
        button.prop('disabled', false);
        button.text('Mesurer');
      },
    });

    let nb_try_mesure = 4;

    function checkMeasureStatus() {
      nb_try_mesure--;
      if (nb_try_mesure === 0) {
        alert(
          'Un problème est survenue dans la mesure, veuillez réésayer plus tard.'
        );
        button.prop('disabled', false);
        button.text('Mesurer');
        return;
      }
      console.log(`checkMeasureStatus for`, taskId, `try`, nb_try_mesure);
      setTimeout(function () {
        $.ajax({
          type: 'GET',
          url: 'https://bff.ecoindex.fr/api/tasks/' + taskId,
          success: function (response) {
            console.log('Response:', response);
            if (response.status === 'SUCCESS') {
              // console.log(response.header);
              button.prop('disabled', false);
              button.text('Mesurer');
              location.reload();
            } else {
              setTimeout(checkMeasureStatus, 15000);
            }
          },
          error: function (xhr, ajaxOptions, thrownError) {
            if (xhr.status === 422 || xhr.status === 425) {
              button.prop('disabled', false);
              button.text('Mesurer');
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
