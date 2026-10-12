<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Cifra las credenciales que fueron guardadas en texto plano
     * antes de agregar el cast 'encrypted' al modelo Project.
     */
    public function up(): void
    {
        foreach (DB::table('projects')->get(['id', 'facebook_access_token', 'wp_application_password']) as $row) {
            $updates = [];

            foreach (['facebook_access_token', 'wp_application_password'] as $column) {
                $value = $row->{$column};

                if ($value === null || $value === '') {
                    continue;
                }

                try {
                    Crypt::decryptString($value);
                    // Ya está cifrado, no hacer nada.
                } catch (DecryptException) {
                    $updates[$column] = Crypt::encryptString($value);
                }
            }

            if ($updates !== []) {
                DB::table('projects')->where('id', $row->id)->update($updates);
            }
        }
    }

    public function down(): void
    {
        // No se revierte: descifrar dejaría credenciales en texto plano.
    }
};
