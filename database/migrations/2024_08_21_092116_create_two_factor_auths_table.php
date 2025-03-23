<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */

     /*
        Stucture of user session

        $_SESSION["{userId}_2FASession"] = [
            "token" => {user token auth},
            "ip_address" => {user ip address from request},
            "otp_code" => {OTP for verify 2FA},
            "state" => {Verification state (pending/verified)},
            "exp_to_verify" => {timestamp for expired OTP to verify}
        ]
     */

    public function up(): void
    {
        Schema::create('two_factor_auths', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->unique()->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
            $table->string('token');
            $table->string('ip_address');
            $table->string('otp_code');
            $table->boolean("state");
            $table->dateTime("exp_to_verify");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('two_factor_auths');
    }
};
