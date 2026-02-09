public function up(): void
{
    Schema::table('articles', function (Blueprint $table) {
        // Campos para la automatización
        $table->string('status')->default('pending'); // pending, generated, published
        $table->date('scheduled_date')->nullable();
        $table->string('keyword')->nullable(); // La palabra clave objetivo
        $table->string('intention')->nullable(); // Informativa / Comercial
    });
}

public function down(): void
{
    Schema::table('articles', function (Blueprint $table) {
        $table->dropColumn(['status', 'scheduled_date', 'keyword', 'intention']);
    });
}