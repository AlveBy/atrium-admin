<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Время генерации страниц публичной части.
 *
 * Из этих строк считаются оба «зелёных» гаджета рабочего стола: «Скорость
 * сайта» (медиана) и «Монитор производительности» (страниц в секунду).
 *
 * Битрикс за этими числами ходил наружу — гаджет скорости постил лицензионный
 * ключ на www.1c-bitrix.ru и получал p50 оттуда, а оценку производительности
 * записывал модуль perfmon после ручного прогона теста. Ни того, ни другого у
 * нас нет, поэтому меряем сами, у себя.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_page_timings', function (Blueprint $table) {
            $table->id();
            $table->string('path', 255);
            $table->unsignedInteger('duration_ms');
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_page_timings');
    }
};
