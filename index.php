<?php
error_reporting(0);
// Підключаємо конектор
include_once 'connector.php';
// Підключаємо стилі
echo "<link rel='stylesheet' href='style/style.css'>";

/* Створюємо об'єкт класу Connector,
	перший параметр - унікальний ключ API,
	другий - унікальний ключ магазину
*/
$api = new Connector('6c5212439a7d9b5c98a91f4b06d9735e','8ab7e4e97832583cfaff36977c4823da');
/* Передаємо параметри:
	start - встановлює номер, з якого починається відлік товаруб.
	count - кількість товару, яку необхідно отримати,
	params - встановлює які поля для товару необхідно отримати
	(в нашому випадку це - унікальний ідентифікатор товару, опис, назва, ціна, зображення та дата модифікації)
	
	P.S. Як я розумію, пагінацію необхідно реалізовувати за допомогою
	параметрів start і count або page_cursor. Але, параметр start 
	приймає тільки 0, при інших значення повертає помилку "Invalid data provided"
	(https://api.api2cart.com/v1.1/product.list.json?api_key=6c5212439a7d9b5c98a91f4b06d9735e&store_key=8ab7e4e97832583cfaff36977c4823da&start=1&count=3&params=force_all).
	А для page_cursor, який приймає в якості значення рядок, ніде не вказано яким чином його необхідно формувати.
	Тому було прийнято рішення отримати максимальну кількість товару (250) і робити пагінацію за допомогою індексів масиву.
*/

// Получаємо теперішню і вчорашню дати
$today = new DateTime(date('Y-m-d'));
$yesterday = new DateTime(date('Y-m-d'));
$yesterday->modify('-1 day');
	
// Получаємо передану із url дату
$date = $_GET['date'];

// Формуємо форму з перемикачами
$form = '<div class="form"><form action="index.php" align=center method="GET">';
	
// Якщо дата із url дорівнює теперішній даті, то перемикач Today являється checked
// аналогічно робимо для інших
($date==$today->format('Y-m-d')) ? $form.='<input name="date" type="radio" value="'.$today->format('Y-m-d').'" checked>Today</input>' : $form.='<input name="date" type="radio" value="'.$today->format('Y-m-d').'">Today</input>';
($date==$yesterday->format('Y-m-d')) ? $form.='<input name="date" type="radio" value="'.$yesterday->format('Y-m-d').'" checked>Yesterday</input>' : $form.='<input name="date" type="radio" value="'.$yesterday->format('Y-m-d').'">Yesterday</input>';
(empty($date)) ? $form.='<input name="date" type="radio" value="" checked>All</input>' : $form.='<input name="date" type="radio" value="">All</input>';
$form.= '<input type="submit" value="Відобразити"></input></form></div>';
	
// Виводимо форму
echo $form;

// Получаємо дату modified_from (today) і modified_to (tomorrow)
$date_from = new DateTime($date);
$date_to = new DateTime($date);
$date_to->modify('+1 day');
// Якщо дата відсутня, то виводимо весь товар, в іншому випадку виводимо товар модифікований в заданий період
if (!empty($date)){
	$params = array(
	  'start'=>'0',
	  'count'=>'250',
	  'params' => 'id,description,name,price,images',
	  'modified_from' => $date_from->format('Y-m-d'),
	  'modified_to' => $date_to->format('Y-m-d')
	);
}else{
	$params = array(
	  'start'=>'0',
	  'count'=>'250',
	  'params' => 'id,description,name,price,images',
	);
}


try {
	// Якщо без винятків
	
	// Формуємо запит. Перший аргумент - метод API2Cart який повертає перелік товару в магазині, другий - параметри
	$result = $api->request('product.list', $params);

	// Кількість товарів на сторінці
	$num = 5;
	
	// Витягаємо поточну строрінку із url
	$page = (int)$_GET['page'];
	// Знаходимо загальну кількість сторінок
	$total_page = (int)(($result->products_count - 1) / $num) + 1;
	
	/* Якщо значення $ page менше одиниці або негативно
	 тоді переходимо на першу сторінку
	 а якщо занадто велике, то переходимо на останню*/
	if(empty($page) or $page < 0) $page = 1;
	if($page > $total_page) $page = $total_page;
	
	// Провіряємо чи потрібні стрілки назад 
	if ($page != 1) $pervpage = '<a href=".?page=1&date='.$date.'" title="На початок"><<</a>
	<a href=".?page='. ($page - 1) .'&date='.$date.'" title="Попередня сторінка"><</a> ';
	// Провіряємо чи потрібні стрілки вперед
	if ($page != $total_page) $nextpage = ' <a href=".?page='. ($page + 1) .'&date='.$date.'" title="Наступна сторінка">></a>
	<a href=".?page=' .$total_page. '&date='.$date.'" title="В кінець">>></a>';

	// Знаходимо дві найближчі сторінки з обох боків
	if($page - 2 > 0) $page2left = ' <a href=".?page='. ($page - 2) .'&date='.$date.'">'. ($page - 2) .'</a> | ';
	if($page - 1 > 0) $page1left = '<a href=".?page='. ($page - 1) .'&date='.$date.'">'. ($page - 1) .'</a> | ';
	if($page + 2 <= $total_page) $page2right = ' | <a href=".?page='. ($page + 2) .'&date='.$date.'">'. ($page + 2) .'</a>';
	if($page + 1 <= $total_page) $page1right = ' | <a href=".?page='. ($page + 1) .'&date='.$date.'">'. ($page + 1) .'</a>';

	// Вивід стрілок
	if ($result->products_count!=0){
		echo '<div align=center class="arrows">'.$pervpage.$page2left.$page1left.'<b>'.$page.'</b>'.$page1right.$page2right.$nextpage.'</div>';
	}else echo "Товар не знайдено!";
	// Вивід контейнера в якому відображаються карточки товару
	echo '<div class="container">';

	// Вивід карточок товару
	for ($i = $num * $page - $num; $i < $num * $page; $i++) {
		// Виводимо в тому випадку, якщо товар має валідний id
		if ($result->product[$i]>0){
			echo '<div class="product-wrap">
					<div class="product-image">
						<a href=""><img src="'.$result->product[$i]->images[0]->http_path.'"></a>
						<div class="product-list">
							<h3>'.$result->product[$i]->name.'</h3>
							<input type="checkbox" id="hd'.$result->product[$i]->id.'" class="hide"/>
							<label for="hd'.$result->product[$i]->id.'" >Опис</label>
							<div id="hd-text">'.$result->product[$i]->description.'</div>
							<div class="price">&#8372;'.$result->product[$i]->price.'</div>
						</div>
					</div>
			</div>';
		}
	}

	echo '</div>';
	
} catch (Exception $e) {
  
  //Якщо з винятками то виводимо повідомленя вийнятка
  echo '#' . $e->getCode() . ' ' . $e->getMessage();

}
