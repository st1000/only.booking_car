<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Highloadblock as HL;
use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\ObjectException;

class OnlyBookingCarComponent extends CBitrixComponent
{
    const USER_DEPARTAMENT_PROP = 'UF_WORK_POS';

    /**
     * onPrepareComponentParams
     *
     * @param  mixed $params
     * @return void
     */
    public function onPrepareComponentParams($params)
    {
        $params["START_TIME"] = htmlspecialchars(trim($_GET["start_time"] ?? ''));
        $params["END_TIME"] = htmlspecialchars(trim($_GET["end_time"] ?? ''));
        $params["CAR_ID"] = (int) ($_GET["car_id"] ?? 0);
        $params["DRIVER"] = (int) ($_GET["driver"] ?? 0);
        $params["CAR_CATEGORY"] = (int) ($_GET["car_category"] ?? 0);
        return $params;
    }

    /**
     * executeComponent
     *
     * @return void
     */
    public function executeComponent()
    {
        if (!$this->checkModules()) {
            ShowError("Не удалось загрузить необходимые модули");
            return;
        }

        global $USER;
        $userId = $USER->GetID();

        $startTime = strtotime($this->arParams["START_TIME"]);
        $endTime = strtotime($this->arParams["END_TIME"]);

        if (!$this->validateTime($startTime, $endTime)) {
            ShowError("Неверный формат времени бронирования");
            return;
        }

        $this->arResult["AVAILABLE_CARS"] = $this->getAvailableCars($startTime, $endTime, $userId);
        $this->includeComponentTemplate();
    }

    /**
     * checkModules
     *
     * @return void
     */
    private function checkModules()
    {
        return Loader::includeModule("highloadblock") && Loader::includeModule("iblock") && Loader::includeModule("main");
    }

    /**
     * validateTime
     *
     * @param  mixed $startTime
     * @param  mixed $endTime
     * @return void
     */
    private function validateTime($startTime, $endTime)
    {
        return $startTime && $endTime && $startTime < $endTime;
    }

    /**
     * getAvailableCars
     *
     * @param  mixed $startTime
     * @param  mixed $endTime
     * @param  mixed $userId
     * @return void
     */
    private function getAvailableCars($startTime, $endTime, $userId)
    {
        $userDepartment = $this->getUserDepartment($userId);
        $carCategories = $this->getCarCategoriesForDepartment($userDepartment);
        $carCategories = array_filter($carCategories);

        if (empty($carCategories)) {
            return [];
        }

        $carCategories = $this->filterCarCategory($carCategories);
        $occupiedCars = $this->getOccupiedCars($startTime, $endTime);

        $carFilter = $this->buildCarFilter($carCategories, $occupiedCars);

        return $this->getCars($carFilter);
    }

    /**
     * filterCarCategory
     *
     * @param  mixed $carCategories
     * @return void
     */
    private function filterCarCategory(array $carCategories)
    {
        $carCategoryFilter = $this->arParams["CAR_CATEGORY"];
        if ($carCategoryFilter > 0 && !in_array($carCategoryFilter, $carCategories)) {
            return [];
        }
        return $carCategoryFilter > 0 ? [$carCategoryFilter] : $carCategories;
    }

    /**
     * getOccupiedCars
     *
     * @param  mixed $startTime
     * @param  mixed $endTime
     * @return void
     */
    private function getOccupiedCars($startTime, $endTime)
    {
        try {
            $startDateTime = DateTime::createFromTimestamp($startTime);
            $endDateTime = DateTime::createFromTimestamp($endTime);
        } catch (Exception $e) {
            throw new ObjectException('Invalid date format: ' . $e->getMessage());
        }

        $filter = [
            'LOGIC' => 'OR',
            [
                '>=UF_START_TIME' => $startDateTime,
                '<=UF_END_TIME' => $endDateTime,
            ],
            [
                '>=UF_END_TIME' => $startDateTime,
                '<=UF_START_TIME' => $endDateTime,
            ],
        ];

        $occupiedCars = [];
        $hlblock = $this->getHighloadBlockEntity();
        $bookingTable = $hlblock->getDataClass();
        $rsData = $bookingTable::getList(['filter' => $filter]);
        while ($item = $rsData->fetch()) {
            $occupiedCars[] = $item['UF_CAR_ID'];
        }

        return $occupiedCars;
    }

    /**
     * buildCarFilter
     *
     * @param  mixed $carCategories
     * @param  mixed $occupiedCars
     * @return void
     */
    private function buildCarFilter($carCategories, $occupiedCars)
    {
        $carFilter = [
            'IBLOCK_ID' => $this->arParams["CAR_IBLOCK_ID"],
            'ACTIVE' => 'Y',
            '!ID' => $occupiedCars,
            'PROPERTY_COMFORT_CATEG' => $carCategories,
        ];

        if ($this->arParams["DRIVER"] > 0) {
            $carFilter['PROPERTY_DRIVER'] = $this->arParams["DRIVER"];
        }

        if ($this->arParams["CAR_ID"] > 0) {
            $carFilter['ID'] = $this->arParams["CAR_ID"];
        }

        return $carFilter;
    }

    /**
     * getCars
     *
     * @param  mixed $carFilter
     * @return void
     */
    private function getCars($carFilter)
    {
        $result = [];
        $res = CIBlockElement::GetList([], $carFilter, false, false, ['ID', 'NAME', 'PROPERTY_COMFORT_CATEG', 'PROPERTY_DRIVER']);
        while ($car = $res->Fetch()) {
            $result[] = [
                'ID' => $car['ID'],
                'NAME' => $car['NAME'],
                'CATEGORY' => $car['PROPERTY_COMFORT_CATEG_VALUE'],
                'DRIVER' => $car['PROPERTY_DRIVER_VALUE'],
            ];
        }
        return $result;
    }

    /**
     * getUserDepartment
     *
     * @param  mixed $userId
     * @return void
     */
    private function getUserDepartment($userId)
    {
        $user = CUser::GetByID($userId)->Fetch();
        return $user[self::USER_DEPARTAMENT_PROP] ?? null;
    }

    /**
     * getCarCategoriesForDepartment
     *
     * @param  mixed $departmentId
     * @return void
     */
    private function getCarCategoriesForDepartment($departmentId)
    {
        $department = CIBlockElement::GetByID($departmentId)->GetNext();
        if (!$department) {
            return [];
        }

        $categories = CIBlockElement::GetProperty($department["IBLOCK_ID"], $department["ID"], [], ["CODE" => "AV_CAR_CATEGORIES"]);
        $categoryIds = [];
        while ($category = $categories->Fetch()) {
            $categoryIds[] = $category['VALUE'];
        }
        return $categoryIds;
    }

    /**
     * getHighloadBlockEntity
     *
     * @return void
     */
    private function getHighloadBlockEntity()
    {
        $hlblock = HL\HighloadBlockTable::getById($this->arParams["LOG_HIBLOCK_ID"])->fetch();
        return HL\HighloadBlockTable::compileEntity($hlblock);
    }
}