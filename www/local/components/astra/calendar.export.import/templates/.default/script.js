BX.ready(function() {
  document.getElementById('exportForm').addEventListener('submit', function(event) {
    event.preventDefault(); // Отменяем стандартное поведение отправки формы
    // Здесь можно добавить код для обработки данных формы


// Создаем объект FormData для сбора данных формы
var formData = new FormData(exportForm);

    // Преобразуем FormData в объект для удобного доступа к данным
    var formDataObject = {};
    formData.forEach(function(value, key) {
        formDataObject[key] = value;
    });
   
 // Здесь можно добавить код для обработки данных формы, например, отправку через AJAX   
            var request = BX.ajax.runComponentAction(
              "astra:calendar.export.import",
              "exportCalendar",
              {
                mode: "class",
                data: {
                  data: formDataObject,
                  sessid: BX.message("bitrix_sessid"),
                },
              }
            );
            // промис в который прийдет ответ
            request.then(
              function (response) {
                //сюда придет ответ с status === 'success'  
               // console.log(response);           

               // Очищаем форму и выводим сообщение
    var exportMessage = document.getElementById('export-message');
    exportMessage.innerHTML = 'Данные экспортированы  в формат '  + formDataObject['format']; 
              }            
            ).catch(error => {
           //   console.error(error);
          });

   });


   /** импорт файла */

   document.getElementById('importButton').addEventListener('click', function(event) {
    event.preventDefault();
    var fileInput = document.getElementById('fileInput');
    var file = fileInput.files[0];

    var reader = new FileReader();


    reader.onload = function(event) {
      var base64String = event.target.result;
      // Теперь вы можете использовать base64String, например, отправить её на сервер
         // Здесь можно добавить код для обработки файла
    var request = BX.ajax.runComponentAction(
      "astra:calendar.export.import",
      "importingCalendar",
      {
        mode: "class",
        method: 'POST',
        data: {
          data: base64String,
          sessid: BX.message("bitrix_sessid"),
        },  
      }
    );
    // промис в который прийдет ответ
    request.then(
      function (response) {
        //сюда придет ответ с status === 'success'  
     //   console.log('Файл успешно загружен:', response);  
      }            
    ).catch(error => {
    //  console.error('Ошибка при загрузке файла:', error);
  });

  };
  
  reader.onerror = function(error) {
   //   console.error('Ошибка при чтении файла: ', error);
  };
  
  reader.readAsDataURL(file); // Чтение файла как Data URL (Base64)
  
});

});