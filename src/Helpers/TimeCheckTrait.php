<?php

namespace Helpers;

trait TimeCheckTrait
{
    private string $currentDate;
    private int $currentDayOfMonth;
    private int $maxDayOfMonth;
    private int $currentWeek;
    private int $currentDayOfWeek;
    private int $currentMonth;
    private int $currentYear;
    private int $currentDayOfYear;
    private int $checkedWeek;
    private int $checkedMonth;
    private int $checkedYear;
    private int $checkedDayOfYear;

    protected function currentDate(): string
    {
        if (empty($this->currentDate)) {
            $this->currentDate = date('Y-m-d H:i:s');
        }
        return $this->currentDate;
    }

    protected function dailyCheck(?string $date): bool
    {
        $this->init();
        return (
            $this->pastDayOfYear($date)
            || $this->pastYear($date)
        );
    }

    protected function weeklyCheck(?string $lastCheckDate, int $checkOn = null): bool
    {
        $this->init();
        //todo
        return (
            (
                $this->pastWeek($lastCheckDate)
                && (
                    (
                        isset($checkOn)
                        && $checkOn >= $this->currentDayOfWeek
                    )
                    || (
                        empty($checkOn)
                        && $this->randomFetch($this->currentDayOfWeek, 7)
                    )
                )
            )
            || (
                $this->currentWeek($lastCheckDate)
                && isset($checkOn)
                && $checkOn <= $this->currentDayOfWeek
                && $this->dayOfWeek($lastCheckDate) < $checkOn
            )
            || $this->pastWeek($lastCheckDate, 1)
            || $this->pastYear($lastCheckDate)
        );
    }

    protected function monthlyCheck(?string $date, int $checkOn = null): bool
    {
        $this->init();
        return (
            (
                $this->pastMonth($date)
                && (
                    (
                        isset($checkOn)
                        && $checkOn >= $this->currentDayOfMonth
                    )
                    || (
                        empty($checkOn)
                        && $this->randomFetch($this->currentDayOfMonth, $this->maxDayOfMonth)
                    )
                )
            )
            || $this->pastMonth($date, 1)
            || $this->pastYear($date)
        );
    }

    private function pastYear(string $date, $minDiff = 0): bool
    {
        if (empty($this->checkedYear)) {
            $this->checkedYear = idate('Y', strtotime($date));
        }
        return $this->currentYear > $this->checkedYear + $minDiff;
    }

    private function pastMonth(string $date, $minDiff = 0): bool
    {
        if (empty($this->checkedMonth)) {
            $this->checkedMonth = idate('m', strtotime($date));
        }
        return $this->currentMonth > $this->checkedMonth + $minDiff;
    }

    private function pastWeek(string $date, $minDiff = 0): bool
    {
        if (empty($this->checkedWeek)) {
            $this->checkedWeek = idate('W', strtotime($date));
        }
        return $this->currentWeek > $this->checkedWeek + $minDiff;
    }

    private function currentWeek(string $date): bool
    {
        if (empty($this->checkedWeek)) {
            $this->checkedWeek = idate('W', strtotime($date));
        }
        return $this->currentWeek === $this->checkedWeek;
    }

    private function pastDayOfYear(string $date, $minDiff = 0): bool
    {
        if (empty($this->checkedDayOfYear)) {
            $this->checkedDayOfYear = idate('z', strtotime($date));
        }
        return $this->currentDayOfYear > $this->checkedDayOfYear + $minDiff;
    }

    private function dayOfWeek(string $date): int {
        return idate('N', strtotime($date));
    }

    private function init(): void
    {
        if (empty($this->currentYear)) {
            $this->currentMonth = idate('m');
            $this->currentWeek = idate('W');
            $this->currentDayOfWeek = idate('N');
            $this->currentDayOfMonth = idate('d');
            $this->maxDayOfMonth = idate('t');
            $this->currentYear = idate('Y');
            $this->currentDayOfYear = idate('z');
        }
        unset($this->checkedMonth);
        unset($this->checkedWeek);
        unset($this->checkedYear);
        unset($this->checkedDayOfYear);
    }

    private function randomFetch(int $currentValue, int $maxValue): bool
    {
        $boosted = $currentValue + 1;
        if ($boosted >= $maxValue) {
            return $currentValue === rand($currentValue, $maxValue);
        } else {
            return $boosted === rand($currentValue, $maxValue);
        }
    }

}