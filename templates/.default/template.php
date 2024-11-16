<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}
?>

<div class="booking-cars">
	<?php if (!empty($arResult["AVAILABLE_CARS"])): ?>
	<h3>Доступные автомобили:</h3>
	<ul>
		<?php foreach ($arResult["AVAILABLE_CARS"] as $car): ?>
		<li>
			<strong>Модель:</strong> <?=$car['NAME']?><br>
			<strong>Категория:</strong> <?=$car['CATEGORY']?><br>
			<strong>Водитель:</strong> <?=$car['DRIVER']?><br>
		</li>
		<?php endforeach;?>
	</ul>
	<?php else: ?>
	<p>Нет доступных автомобилей на указанные даты.</p>
	<?php endif;?>

</div>