<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * English strings for clickmeeting
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod_clickmeeting
 * @copyright  2024 Clickmeeting
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['modulename'] = 'ClickMeeting';
$string['modulenameplural'] = 'ClickMeeting';
$string['modulename_help'] = 'Zaplanuj swoje spotkanie używając aplikacji ClickMeeting. <br /> Aplikacja ClickMeeting pozwala Ci bezpośrednio z platformy Moodle, zaprosić swoich studentów na spotkanie.';
$string['clickmeetingfieldset'] = 'Custom example fieldset';
$string['clickmeetingname'] = 'Nazwa konferencji';
$string['clickmeetingname_help'] = 'This is the content of the help tooltip associated with the Clickmeetingname field. Markdown syntax is supported.';
$string['clickmeeting'] = 'ClickMeeting';
$string['pluginadministration'] = 'Administracja ClickMeeting';
$string['pluginname'] = 'ClickMeeting';


$string['settings:apiurl'] = 'API url';
$string['settings:apiurldesc'] = 'ClickMeeting API url';
$string['settings:apikey'] = 'Wpisz lub wklej <br />API KEY tutaj';
$string['settings:apikeydesc'] = 'Aby uzyskać więcej informacji na temat API ClickMeeting, odwiedź <a href="http://dev.clickmeeting.com" target="_blank">dev.clickmeeting.com</a>';

$string['form:waitingmsg'] = 'Wiadomość w "Waiting room"';
$string['form:waitingmsg_help'] = 'Wiadomość pokazywana, w oczekiwaniu na rozpoczęcie konferencji. Maksymalna długość 255 znaków.';
$string['form:maxduration'] = 'Czas trwania';
$string['form:maxduration_help'] = 'Maksymalny czas trwania w godzinach';
$string['form:data_header'] = 'Czas konferencji';
$string['form:select_room_type'] = 'Rodzaj wydarzenia';
$string['form:webinar'] = 'Konferencja';
$string['form:meeting'] = 'Spotkanie';
$string['form:access_type'] = 'Tryb dostępu';
$string['form:open'] = 'Otwarta';
$string['form:password'] = 'Hasło';
$string['form:token'] = 'Token';
$string['form:no_sessions'] = 'Brak wolnego terminu';

$string['view:action'] = 'Akcja';
$string['view:password'] = 'Hasło';
$string['view:room_name'] = 'Nazwa pokoju';
$string['view:room_date'] = 'Data spotkania';
$string['view:joinmeeting'] = 'Dołącz do spotkania';
$string['view:embed'] = 'Umieść pokój na swojej stronie';
$string['view:room_description'] = 'Opis';
$string['view:copy_to_clipboard'] = 'Kopiuj do schowka';
$string['view:oldmeeting'] = 'To wydarzenie juz się odbyło';

$string['clickmeeting:addinstance'] = 'Dodawanie';
$string['clickmeeting:host'] = 'Organizator';
$string['clickmeeting:listener'] = 'Uczestnik';
$string['clickmeeting:presenter'] = 'Prezenter';

$string['startdate_booked'] = 'Nie można utworzyć konferencji dla tej daty';
$string['update_error'] = 'Błąd przy aktualizacji wpisu';
$string['api_404_error'] = '404 Not Found';

$string['privacy:metadata:clickmeeting_api'] = 'W celu zachowania poprawnej integracji z Clickmeeting API, następuje przekazanie danych uczestników do zewnętrznego systemu';
$string['privacy:metadata:clickmeeting_api:email'] = 'Adres e-mail jest wysyłany do zewnętrznego systemu w celu poprawnego dołaczenia do wydarzenia';
$string['privacy:metadata:clickmeeting_api:nickname'] = 'Pełne imię i nazwisko jest wysyłane do zewnętrznego systemu w celu poprawnego dołaczenia do wydarzenia';

$string['privacy:metadata:clickmeetingtokens:userid'] = 'Token dostępowy do wydarzenia przechowywany w kontekście użytkownika';
$string['privacy:metadata:clickmeetingtokens'] = 'Moduł przechowuje listę wygenerowanych tokenów dla uczestników';
