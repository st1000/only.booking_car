<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

CBitrixComponent::includeComponentClass("only:booking_car");

$component = new OnlyBookingCarComponent();
$component->executeComponent();