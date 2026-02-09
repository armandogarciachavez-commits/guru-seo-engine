public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            if (!Schema::hasColumn('articles', 'content')) {
                // longText permite guardar artículos muy largos con HTML
                $table->longText('content')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn('content');
        });
    }