<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contacts')) {
            return;
        }

        Schema::table('contacts', function (Blueprint $table) {
            if (! Schema::hasColumn('contacts', 'customer_price_list_id')) {
                $table->unsignedBigInteger('customer_price_list_id')->nullable()->index()->after('customer_currency_code');
            }
        });

        if (Schema::hasTable('sales_price_lists')) {
            DB::table('contacts')
                ->whereNotNull('price_list_name')
                ->where('price_list_name', '<>', '')
                ->whereNull('customer_price_list_id')
                ->orderBy('id')
                ->chunkById(200, function ($contacts) {
                    foreach ($contacts as $contact) {
                        $companyId = (int) ($contact->company_id ?? 0);
                        $name = trim((string) ($contact->price_list_name ?? ''));

                        if ($name === '') {
                            continue;
                        }

                        $query = DB::table('sales_price_lists')
                            ->where('name', $name);

                        if ($companyId > 0 && Schema::hasColumn('sales_price_lists', 'company_id')) {
                            $query->where(function ($q) use ($companyId) {
                                $q->where('company_id', $companyId)
                                  ->orWhereNull('company_id');
                            });
                        }

                        if (Schema::hasColumn('sales_price_lists', 'is_active')) {
                            $query->where('is_active', true);
                        }

                        $priceListId = (int) ($query->orderByDesc('company_id')->value('id') ?? 0);

                        if ($priceListId > 0) {
                            DB::table('contacts')
                                ->where('id', $contact->id)
                                ->update(['customer_price_list_id' => $priceListId]);
                        }
                    }
                });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('contacts')) {
            return;
        }

        Schema::table('contacts', function (Blueprint $table) {
            if (Schema::hasColumn('contacts', 'customer_price_list_id')) {
                $table->dropColumn('customer_price_list_id');
            }
        });
    }
};
