<? if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;
use Bitrix\Main\SystemException;
use \Bitrix\Main\Engine\Contract\Controllerable;
use \Bitrix\Calendar\Internals\EventTable;


class CalendarExportImport extends CBitrixComponent implements Controllerable
{

    public function executeComponent()
    {
        try {
            // подключаем шаблон
            $this->IncludeComponentTemplate();
        } catch (SystemException $e) {
            ShowError($e->getMessage());
        }
    }

    // обязательный метод предпроверки данных
    public function configureActions()
    {
        // сбрасываем фильтры по-умолчанию (Bitrix\Main\Engine\ActionFilter\Authentication() и Bitrix\Main\Engine\ActionFilter\HttpMethod() и Bitrix\Main\Engine\ActionFilter\Csrf()), предустановленные фильтры находятся в папке /bitrix/modules/main/lib/engine/actionfilter/
        return [
            'importingCalendar' => [
                'prefilters' => [],
                'postfilters' => []
            ]
        ];
    }

    //экспорт календаря в файлы
    public function exportCalendarAction($data)
    {
        $this->checkModules();

        // // Первое число текущего месяца
        $startDate = date('01.m.Y');

        // Последний день текущего месяца
        $endDate = date('t.m.Y');

        // Получить список событий
        $events = \Bitrix\Calendar\Internals\EventTable::getList([
            'filter' => [
                'CAL_TYPE' => 'company_calendar', // Фильтр по типу календаря (company)
                '>=DATE_FROM' =>  $startDate, // События, начиная с 1 октября 2023
                '<=DATE_TO'   => $endDate, // События до 31 октября 2023
                'DELETED' => 'N', // Только не удаленные события
            ],
            'select' => ['ID', 'NAME', 'MEETING', 'CREATED_BY', 'DATE_FROM', 'DAV_XML_ID'],
        ])->fetchAll();

        // Сохранение данных на диск Битрикс24

        // Создаем временный файл
        if ($data['format'] == 'csv') {
            $fileName = 'calendar.csv';
            $headerText = "NAME, LAST NAME, EVENT NAME, EXTERNAL CODE, DATE OF THE EVENT\n";
            $bodyText = "";

            foreach ($events as $key => $event) {
                $arText = $this->firstLastNameDate($event);
                $bodyText .=  $arText['NAME'] . ", " . $arText['LAST NAME'] . ", " . $event['NAME'] . ", " . $event['DAV_XML_ID'] . ", " . $arText['DATE_EVENT'] . "\n";
            }

            $fileContent = $headerText . $bodyText;
        } else if ($data['format'] == 'xml') {
            $fileName = 'calendar.xml';
            $headerText = '<?xml version="1.0" encoding="UTF-8"?>
<events>';
            $bodyText = "";
            foreach ($events as $key => $event) {
                $arText = $this->firstLastNameDate($event);
                $bodyText .= "<event>
        <name>" . $arText['NAME'] . "</name>
        <last_name>" . $arText['LAST NAME'] . "</last_name>
        <event_name>" .
                    $event['NAME'] . "</event_name>
        <external_code>" . $event['DAV_XML_ID'] . "</external_code>
        <date_of_the_event>" . $arText['DATE_EVENT'] . "</date_of_the_event>
    </event>";
            }

            $footerText = "</events>";
            $fileContent = $headerText . $bodyText . $footerText;
        } else if ($data['format'] == 'json') {
            $fileName = 'calendar.json';
            foreach ($events as $key => $event) {
                $arText = $this->firstLastNameDate($event);

                $arForJson[$key] = array(
                    'name' => $arText['NAME'],
                    'last_name' => $arText['LAST NAME'],
                    'event_name' => $event['NAME'],
                    'external_code' => $event['DAV_XML_ID'],
                    'date_of_the_event' => $arText['DATE_EVENT']
                );
            }
            $fileContent = json_encode($arForJson);
        }

        $tempFilePath = sys_get_temp_dir() . '/' . $fileName;

        file_put_contents($tempFilePath, $fileContent);

        // Получаем хранилище диска
        $storage = \Bitrix\Disk\Driver::getInstance()->getStorageByUserId(1);

        if ($storage) {
            //поиск папки по названию
            $folder = $storage->getChild(
                array(
                    '=NAME' => 'Экспорт событий',
                    'TYPE' => \Bitrix\Disk\Internals\FolderTable::TYPE_FOLDER
                )
            );

            if (!$folder) {
                // Создать папку в хранилище
                $folder = $storage->addFolder(
                    array(
                        'NAME' => 'Экспорт событий',
                        'CREATED_BY' => 1
                    )
                );
            }

            //сохранить файл в хранилище
            $fileArray = \CFile::MakeFileArray($tempFilePath);
            $file = $folder->uploadFile($fileArray, array(
                'CREATED_BY' => 1
            ));
        } else {
            echo "Хранилище не найдено.";
        }

        // Удаляем временный файл
        unlink($tempFilePath);
    }

    // импорт календаря из json файла
    public function importingCalendarAction($data)
    {
        $this->checkModules();

        $base64Data = $data; // Например, поле с именем "file_data"

        // Убираем префикс "data:image/png;base64,", если он есть
        if (strpos($base64Data, 'base64,') !== false) {
            $base64Data = explode('base64,', $base64Data)[1];
        }

        // 1. Декодируем Base64
        $jsonString = base64_decode($base64Data);
        // 2. Преобразуем JSON в массив
        $dataArray = json_decode($jsonString, true); // true — для преобразования в ассоциативный массив

        foreach ($dataArray as $key => $event) {
            // Имя и фамилия сотрудника
            $firstName = $event['name']; // Имя
            $lastName = $event['last_name']; // Фамилия

            // Получаем ID сотрудника
            $user = Bitrix\Main\UserTable::getList([
                'filter' => [
                    'NAME' => $firstName, // Фильтр по имени
                    'LAST_NAME' => $lastName, // Фильтр по фамилии
                ],
                'select' => ['ID'], // Выбираем только ID
                'limit' => 1, // Ограничиваем результат одним пользователем
            ])->fetch();

            //если пользователя нет, создать нового пользователя
            if (!$user['ID']) {
                // Данные нового пользователя
                $strong_password = uniqid();

                $userData = [
                    'LOGIN' => uniqid(), // Логин (обязательно)
                    'EMAIL' => uniqid() . '@example.com', // Email (обязательно)
                    'NAME' => $firstName, // Имя
                    'LAST_NAME' => $lastName, // Фамилия
                    'PASSWORD' => $strong_password, // Пароль
                    'CONFIRM_PASSWORD' => $strong_password, // Подтверждение пароля
                    'ACTIVE' => 'Y', // Активен ли пользователь (Y/N)
                    'GROUP_ID' => array(12, 11), // Группы пользователя
                ];

                // Создаем объект CUser
                $user = new CUser;

                // Создаем пользователя
                $userId = $user->Add($userData);
                $user['ID'] = $userId;
                unset($user);
            }

            //проверка существует ли уже событие в календаре
            $eventExist = \Bitrix\Calendar\Internals\EventTable::getList([
                'filter' => [
                    'DAV_XML_ID' => $event['external_code'], // Фильтр по типу календаря (company)
                    'DELETED' => 'N', // Только не удаленные события
                ],
                'select' => ['DAV_XML_ID'],
            ])->fetchAll();

            if (!$eventExist) {
                $dateEvent = new \DateTime($event['date_of_the_event']);
                $dateFrom = $dateEvent->format("d.m.Y H:i:s");

                // Данные для создания события
                $eventFields = [
                    'NAME' => $event['event_name'], // Название события
                    'DATE_FROM' => $dateFrom, // Дата и время начала (в формате YYYY-MM-DD HH:MI:SS)
                    'DATE_TO' => $dateFrom, // Дата и время окончания
                    'CAL_TYPE' => 'company_calendar', // Тип календаря: user, group, company
                    'CREATED_BY' => $user['ID'], // ID пользователя, который создает событие
                    'ACTIVE' => 'Y', // Активность события
                    'DAV_XML_ID' => $event['external_code'] // Внешний код для связи с другими системами
                ];

                // Создаем событие
                $eventId = \CCalendar::SaveEvent([
                    'arFields' => $eventFields,
                    'userId' => 1, // ID пользователя, от имени которого создается событие
                ]);

                if ($eventId) {
                    // \Bitrix\Main\Diag\Debug::writeToFile("Событие успешно создано. ID события: " . $eventId, $varName = __DIR__,$fileName
                    //= "/local/debug/debug.log");
                } else {
                    // \Bitrix\Main\Diag\Debug::writeToFile("Ошибка при создании события.", $varName = __DIR__,
                    //$fileName="/local/debug/debug.log");
                }
            }
        }

        // $this->deleteAllEvents();
    }

    /*
protected
*/
    // проверяем установку модуля «Информационные блоки» (метод подключается внутри класса try...catch)
    protected function checkModules()
    {
        // если модуль не подключен
        if (!Loader::includeModule('iblock'))
            // выводим сообщение в catch
            throw new SystemException(Loc::getMessage('IBLOCK_MODULE_NOT_INSTALLED'));

        if (!Loader::includeModule('calendar'))
            // выводим сообщение в catch
            throw new SystemException(Loc::getMessage('IBLOCK_MODULE_NOT_INSTALLED'));

        if (!Loader::includeModule('disk'))
            // выводим сообщение в catch
            throw new SystemException(Loc::getMessage('IBLOCK_MODULE_NOT_INSTALLED'));

        if (!Loader::includeModule('main'))
            // выводим сообщение в catch
            throw new SystemException(Loc::getMessage('IBLOCK_MODULE_NOT_INSTALLED'));
    }

    /*
private
*/
    private function firstLastNameDate($event)
    {
        $arFirstLastNameDate = array();

        // имя фамилия
        $arMeeting = unserialize($event['MEETING']);
        $str = $arMeeting['HOST_NAME'];
        $pos = strrpos($str, ' ');
        $arFirstLastNameDate['NAME'] = substr($str, 0, $pos);
        $arFirstLastNameDate['LAST NAME'] = substr($str, $pos + 1);

        // дата события
        $dateFrom = $event['DATE_FROM'];
        $arFirstLastNameDate['DATE_EVENT'] = $dateFrom->format('d-m-Y H:i:s');

        return $arFirstLastNameDate;
    }

    // удалить все события в календаре
    private function deleteAllEvents()
    {
        // Получаем список всех событий
        $events = EventTable::getList([
            'select' => ['ID'], // Выбираем только ID событий
        ]);

        // Удаляем каждое событие
        while ($event = $events->fetch()) {
            $result = EventTable::delete($event['ID']); // Удаляем событие по ID
            if ($result->isSuccess()) {
                // \Bitrix\Main\Diag\Debug::writeToFile("Событие с ID {$event['ID']} успешно удалено.<br>", $varName = __DIR__,
                //$fileName ="/local/debug/debug.log");
            } else {
                // \Bitrix\Main\Diag\Debug::writeToFile("Ошибка при удалении события с ID {$event['ID']}: " . implode( ',
                //',$result->getErrorMessages()) . "<br>", $varName = __DIR__, $fileName = "/local/debug/debug.log");
            }
        }
    }
}
