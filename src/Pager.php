<?php
namespace samson\pager;

use samson\core\iModuleViewable;



/**
 * SamsonPager - Модуль для постраничного вывода и управления информацией
 *
 * @author Vitaly Iegorov <vitalyiegorov@gmail.com>
 * @author Nikita Kotenko <nick.w2r@gmail.com>
 *
 * @version 0.0.2
 */
class Pager implements iModuleViewable
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
	 * @param number 	$current_page 	Номер текущей строки данных ( от 1 до $rows_count )  	 
     * @param number 	$page_size 		Количество строк данных отображаемых на одной странице
     * @param string 	$url_prefix		Url prefix for pagination links
     * @param number 	$rows_count 	Общее количество строк данных
	 */
	public function __construct( $current_page = NULL, $page_size = NULL, $url_prefix = NULL, $rows_count = NULL, $getParams = NULL )
	{	
		// TODO убрать
		// Если переданна требуемая страница
		if (isset( $current_page )) 
		{			
			// Установим текущую страницу
			$this->current_page = $current_page;
			// Запишем текущую страницу в сессию
			$_SESSION['SamsonPager_current_page'] = $current_page;
		}
		// Если есть запись в сассии получим текущию страницу
		//else if ( isset($_SESSION['SamsonPager_current_page']) )$this->current_page = $_SESSION['SamsonPager_current_page'];
		else $this->current_page = 1;		
		
		// Безопасно получим размер страницы данных
		$this->page_size = isset( $page_size ) ? $page_size : $this->page_size;

		// Безопасно получим префикс для ссылок
		$this->url_prefix = locale_path().(isset( $url_prefix ) ? $url_prefix : $this->url_prefix);

		// Рассчитаем параметры вывода
		$this->update($rows_count);

        $this->getParams = $getParams;
	}

	/**
	 * Рассчитать параметры по-страничного вывода
	 * @param number $rows_count Общее количество строк данных
	 */
	public function update( $rows_count = NULL )
	{	
		// Безопасно получим общее кво строк данных
		$this->rows_count = isset( $rows_count ) ? $rows_count : $this->rows_count;		
		
		// Посчитаем общее кво страниц
		$this->total = ceil( $this->rows_count / $this->page_size );	
				
		// If page is out limits - fix it 
		if( $rows_count > 0 )
		{
			if( $this->current_page > $this->total ) $this->current_page = $this->total;
			else if( $this->current_page < 0 ) $this->current_page = 1;
		}
		
		// Для расчетов, нумерация страниц начинается с 0
		$this->_page_num = $this->current_page == 0 ? 0 : $this->current_page - 1;	
		
		// Рассчитаем номер первого выводимой строки данных
		$this->start = $this->_page_num * $this->page_size;		
		
		// Рассчитаем номер последней выводимой строки данных
		$this->end = $this->_page_num == $this->total - 1 ? 
			$this->rows_count - (($this->total - 1) * $this->page_size) : $this->page_size;
		
		//trace('row_count:'.$this->rows_count.'pn:'.$this->_page_num.' total:'.$this->total.' end:'.$this->end.' start:'.$this->start);
		
		// Если передан идентификатор вывода всех записей
		if( $this->current_page == self::ALL_DATA )
		{
			$this->start = 0;
			$this->end = $this->rows_count;
		}
		else if ($this->rows_count == 0)
		{
			$this->start = 0;
			$this->end = 0;
		}
		
		// Сформируем массив с номера страниц для представления, всегда есть первая страница
		$this->html_pages = array( '1' => '1' );
		
		// Рассчитаем крайнее наименьшее значение относительно $page_num
		$start = ($this->current_page - 2) > 1 ? $this->current_page - 2 : 2;
		
		// Рассчитаем крайнее наибольшее значение относительно $page_num
		$end = ($this->current_page + 2) < $this->total ? $this->current_page + 2 : $this->total - 1;

		// Пробежимся между крайними значениями и запишем их в представление
		for ($i = $start; $i <= $end; $i++) $this->html_pages[ $i ] = $i;		

		// Всегда добавляем последнюю страницу
		if($this->total>1) {
            $this->html_pages[ '...' ] = '...';
            $this->html_pages[ $this->total ] = $this->total;
        }
		
		if($this->total > $this->current_page) $this->next = $this->current_page+1;
		else $this->next = 0;

		if($this->current_page > 1) $this->prev = $this->current_page-1;
		else $this->prev = 0;
		
		//trace('this->current_page - '.$this->current_page);
		//trace($this);
		// Вернем указатель на самого себя
		return $this;
	}
	
	/**
	 * Вывести HTML представление для управления по-страничным выводом данных
	 * @param string $url Начальная часть URL для правильного подключение функционала 
	 */
	public function toHTML()
	{
		// Результат
		$html = '<script type="text/javascript">var SamsonPager = { currentPage : '.$this->current_page.' }; </script>';	
		
		// Определим если єто текущая страница
		$active = (('0' == $this->current_page) || (sizeof($this->html_pages) == 1)) ? 'active' : '';		

        // If there is previous page - render button
		if ($this->prev != 0) {
            $url = url()->build( $this->url_prefix, $this->prev);
            if (!empty($this->getParams)) {
                $url .= '?';
                foreach($this->getParams as $parameterIndex => $parameterValue) {
                    $url .= $parameterIndex.'='.$parameterValue.'&';
                }
                $url = substr($url, 0, strlen($url) - 1);
            }
            $html .= m('pager')
                ->desc($this->prevTitle)
                ->url($url)
                ->output('prev_li.php');
        }
		
		
		// Пункт меню для вывода всех строк данных
		$all_html = class_exists('samson\i18n\i18n') ? t('Все',true) : 'Все';
		$html .= '<li><a class="__samson_pager_li '.$active.' __samson_pager_all" href="'.url()->build( $this->url_prefix, 0 ).'/">'.$all_html.'</a></li>';
		
		// Переберем данные для представления
		if(sizeof($this->html_pages) > 1) foreach ( $this->html_pages as $n => $p ) 
		{
			// Определим если єто текущая страница
            $view = ($n == $this->current_page) ? 'current_li.php' : 'li.php';

			if ($n!='...'){
                $url = url()->build( $this->url_prefix, $n ).'/';
                if ($n==1) {
                    $url = url()->build( $this->url_prefix );
                } else {
                    $url = substr($url, 0, strlen($url) - 1);
                }
                if (!empty($this->getParams)) {
                    $url .= '?';
                    foreach($this->getParams as $parameterIndex => $parameterValue) {
                        $url .= $parameterIndex.'='.$parameterValue.'&';
                    }
                    $url = substr($url, 0, strlen($url) - 1);
                }
                // Сформируем представление
                $html .= m('pager')	// Получим модуль постраничного вывода
                    ->set( 'class',$active  ) 	// Если это текущая страница
                    ->set( 'page_view', $p )  	// Установим представление
                    ->set( 'url',  $url)// Установим "правильную" ссылку
                    ->output($view);// Выведем представление
            } else {
                $html.='<li><span>...</span></li>';
            }
		}

        // If there is next page - render button
		if ($this->next != 0) {
            $url = url()->build( $this->url_prefix, $this->next);
            if (!empty($this->getParams)) {
                $url .= '?';
                foreach($this->getParams as $parameterIndex => $parameterValue) {
                    $url .= $parameterIndex.'='.$parameterValue.'&';
                }
                $url = substr($url, 0, strlen($url) - 1);
            }
            $html .= m('pager')
                ->desc($this->nextTitle)
                ->url($url)
                ->output('next_li.php');
        }
		
		// Вернем то что получили
		return $html;
	}	
	
	/**
	 * @see iModuleViewable::toView()
	 */
	public function toView( $prefix = NULL, array $restricted = array() )
	{	
		// Результат
		$values = array();
		
		// Пробежимся по переменным класса
		foreach( get_object_vars( $this ) as $var => $value ) $values[ $prefix.$var ] = $value;	
		// Сгенерируем HTML представление
		$values[ $prefix.'html' ] = $this->toHTML(); 
	
		// Вернем коллекцию
		return $values;
	}
}
