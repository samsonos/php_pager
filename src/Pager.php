<?php
namespace samson\pager;

use samson\core\iModuleViewable;
use samsonframework\pager\PagerInterface;

/**
 * SamsonPager - Модуль для постраничного вывода и управления информацией
 *
 * @author Vitaly Iegorov <vitalyiegorov@gmail.com>
 * @author Nikita Kotenko <nick.w2r@gmail.com>
 *
 * @version 0.0.2
 */
class Pager implements iModuleViewable, PagerInterface
{
    /** Идентификатор вывода всех строк данных */
    const ALL_DATA = 0;

    /**
     * Номер текущей строки данных ( от 1 до $rows_count )
     * @var number
     */
    public $current_page = 1;

    /**
     * Общее количество строк данных
     * @var number
     */
    public $rows_count = 20;

    /**
     * Количество строк данных отображаемых на одной странице ( по умолчанию 20 )
     * @var number
     */
    public $page_size = 20;

    /**
     * Префикс URL для подстановки в ссылки переключателя
     * @var string
     */
    public $url_prefix = '';

    /**
     * Расчетный параметр определяющий номер первой выводимой строки данных
     * для текущей отображаемой страницы данных
     * @var number
     */
    public $start = 0;

    /**
     * Общее количество страниц данных
     * @var number
     */
    public $total = 0;

    /**
     * Расчетный параметр определяющий номер последней выводимой строки данных
     * для текущей отображаемой страницы данных
     * @var number
     */
    public $end = 0;

    /**
     * Расчетный параметр определяющий номер следующей страницы
     * @var number
     */
    public $next = 0;

    /**
     * Расчетный параметр определяющий номер предыдущей страницы
     * @var number
     */
    public $prev = 0;

    /**
     * Коллекция страниц для HTML представления
     * @var array
     */
    protected $html_pages = array();

    /**
     * Номер текущей строки данных ( от 0 до ($rows_count-1) )
     * Используется для внутренних расчетов
     * @var number
     */
    protected $_page_num;

    /** @var string Title of the next page button */
    public $nextTitle = 'Go to next page';

    /** @var string Title of the previous page button */
    public $prevTitle = 'Go to next page';

    /** @var array Get parameters which will be added to URL */
    public $getParams = array();


    /**
     * Конструктор
     *
     * @param number $currentPage Номер текущей строки данных ( от 1 до $rows_count )
     * @param number $pageSize Количество строк данных отображаемых на одной странице
     * @param string $urlPrefix Url prefix for pagination links
     * @param number $rowsCount Общее количество строк данных
     * @param array $getParams Array of GET parameters
     */
    public function __construct(
        $currentPage = null,
        $pageSize = null,
        $urlPrefix = null,
        $rowsCount = null,
        $getParams = array()
    ) {
        // TODO убрать
        // Если переданна требуемая страница
        if (isset($currentPage)) {
            // Установим текущую страницу
            $this->current_page = $currentPage;
            // Запишем текущую страницу в сессию
            $_SESSION['SamsonPager_current_page'] = $currentPage;
        } else {
            $this->current_page = 1;
        }
// Если есть запись в сассии получим текущию страницу
//else if ( isset($_SESSION['SamsonPager_current_page']) )$this->current_page = $_SESSION['SamsonPager_current_page'];
        // Безопасно получим размер страницы данных
        $this->page_size = isset($pageSize) ? $pageSize : $this->page_size;

        // Безопасно получим префикс для ссылок
        $this->url_prefix = locale_path() . (isset($urlPrefix) ? $urlPrefix : $this->url_prefix);

        // Рассчитаем параметры вывода
        $this->update($rowsCount);

        $this->getParams = !empty($getParams) ? $getParams : $_GET;
    }

    /**
     * Рассчитать параметры по-страничного вывода
     * @param number $rows_count Общее количество строк данных
     * @return self Chaining
     */
    public function update($rows_count = null)
    {
        // Безопасно получим общее кво строк данных
        $this->rows_count = isset($rows_count) ? $rows_count : $this->rows_count;

        // Посчитаем общее кво страниц
        $this->total = ceil($this->rows_count / $this->page_size);

        // If page is out limits - fix it
        if ($rows_count > 0) {
            if ($this->current_page > $this->total) {
                $this->current_page = $this->total;
            } elseif ($this->current_page < 0) $this->current_page = 1;
        }

        // Для расчетов, нумерация страниц начинается с 0
        $this->_page_num = $this->current_page == 0 ? 0 : $this->current_page - 1;

        // Рассчитаем номер первого выводимой строки данных
        $this->start = $this->_page_num * $this->page_size;

        // Рассчитаем номер последней выводимой строки данных
        $this->end = $this->_page_num == $this->total - 1 ?
            $this->rows_count - (($this->total - 1) * $this->page_size) : $this->page_size;

        // Если передан идентификатор вывода всех записей
        if ($this->current_page == self::ALL_DATA) {
            $this->start = 0;
            $this->end = $this->rows_count;
        } elseif ($this->rows_count == 0) {
            $this->start = 0;
            $this->end = 0;
        }

        // Сформируем массив с номера страниц для представления, всегда есть первая страница
        $this->html_pages = array('1' => '1');

        // Рассчитаем крайнее наименьшее значение относительно $page_num
        $start = ($this->current_page - 2) > 1 ? $this->current_page - 2 : 2;

        // Рассчитаем крайнее наибольшее значение относительно $page_num
        $end = ($this->current_page + 2) < $this->total ? $this->current_page + 2 : $this->total - 1;

        // Пробежимся между крайними значениями и запишем их в представление
        for ($i = $start; $i <= $end; $i++) {
            $this->html_pages[$i] = $i;
        }

        // Всегда добавляем последнюю страницу
        if ($this->total > 1) {
            $this->html_pages['...'] = '...';
            $this->html_pages[$this->total] = $this->total;
        }

        if ($this->total > $this->current_page) {
            $this->next = $this->current_page + 1;
        } else {
            $this->next = 0;
        }

        if ($this->current_page > 1) {
            $this->prev = $this->current_page - 1;
        } else {
            $this->prev = 0;
        }

        //trace('this->current_page - '.$this->current_page);
        //trace($this);
        // Вернем указатель на самого себя
        return $this;
    }

    /**
     * Вывести HTML представление для управления по-страничным выводом данных
     * @return Pager HTML
     */
    public function toHTML()
    {
        // Результат
        $html = '<script type="text/javascript">var SamsonPager = { currentPage : ' . $this->current_page .
            ' }; </script>';

        // Определим если єто текущая страница
        $active = (('0' == $this->current_page) || (sizeof($this->html_pages) == 1)) ? 'active' : '';

        // If there is previous page - render button
        if ($this->prev != 0) {
            $url = url()->build($this->url_prefix, $this->prev);
            // Add get parameters to url
            $url .= $this->buildGetParameters();
            $html .= m('pager')
                ->view('prev_li')
                ->set('desc', $this->prevTitle)
                ->set('url', $url)
                ->output();
        }


        // Пункт меню для вывода всех строк данных
        $allHTML = class_exists('samsonphp\i18n\i18n', false) ? t('Все', true) : 'Все';
        $html .= '<li><a class="__samson_pager_li ' . $active .
            ' __samson_pager_all" href="' . url()->build($this->url_prefix, 0) . '/">' . $allHTML . '</a></li>';

        // Переберем данные для представления
        foreach ($this->html_pages as $n => $p) {
            // Определим если єто текущая страница
            $view = ($n == $this->current_page) ? 'current_li.php' : 'li.php';

            if ($n != '...') {
                $url = ($n == 1) ? url()->build($this->url_prefix) : url()->build($this->url_prefix, $n);
                // Add get parameters to url
                $url .= $this->buildGetParameters();
                // Сформируем представление
                $html .= m('pager')// Получим модуль постраничного вывода
                ->view($view)
                    ->set('class', $active)// Если это текущая страница
                    ->set('page_view', $p)// Установим представление
                    ->set('url', $url)// Установим "правильную" ссылку
                    ->output();// Выведем представление
            } else {
                $html .= '<li><span>...</span></li>';
            }
        }

        // If there is next page - render button
        if ($this->next != 0) {
            $url = url()->build($this->url_prefix, $this->next);
            // Add get parameters to url
            $url .= $this->buildGetParameters();
            $html .= m('pager')
                ->view('next_li')
                ->set('desc', $this->nextTitle)
                ->set('url', $url)
                ->output();
        }

        // Вернем то что получили
        return $html;
    }

    protected function buildGetParameters()
    {
        $parametersString = '?';
        foreach ($this->getParams as $parameterIndex => $parameterValue) {
            $parametersString .= $parameterIndex . '=' . $parameterValue . '&';
        }
        return substr($parametersString, 0, strlen($parametersString) - 1);
    }

    /** {@inheritdoc} */
    public function toView($prefix = null, array $restricted = array())
    {
        // Результат
        $values = array();

        // Пробежимся по переменным класса
        foreach (get_object_vars($this) as $var => $value) {
            $values[$prefix . $var] = $value;
        }
        // Сгенерируем HTML представление
        $values[$prefix . 'html'] = $this->toHTML();

        // Вернем коллекцию
        return $values;
    }
}
