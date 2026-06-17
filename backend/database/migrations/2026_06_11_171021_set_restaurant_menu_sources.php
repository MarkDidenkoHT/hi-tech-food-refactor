<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The legacy hardcoded Santorini menu, shared by Подворье and Бассейн.
     *
     * @var array<int, string>
     */
    private const SANTORINI_MENU = [
        'Селедочка под водочку',
        'Тарелка от фермерских сыроваров',
        'Ассорти мясных деликатесов',
        'Пивное плато',
        'Овощная нарезка Гопачек',
        'Соления из погребка',
        'Смалец',
        'Икра из печеных баклажанов с овечьей брынзой и черным хлебом',
        'Тещин язык',
        'Оливье с говядиной',
        'Салат с картошкой пай',
        'Сельдь под шубой',
        'Шопский',
        'Теплый салат с куриным филе с гренками',
        'Деруны с картофелем и сметаной',
        'Вареники с картошкой',
        'Вареники с капустой',
        'Вареники с творогом',
        'Вареники с вишней',
        'Пельмени',
        'Жульен',
        'Котлета по киевски',
        'Мититеи с гарниром',
        'Свиная костица с овощами',
        'Шашлык свиной',
        'Шашлык куриный',
        'Картофель жареный с грибами',
        'Овощи гриль',
        'Голубцы из крыжалок',
        'Голубцы с виноградным листом',
        'Жаркое с свиными ребрами',
        'Блинчики с курицей',
        'Борщ с пампушками',
        'Зама',
        'Окрошка с говядиной',
        'Солянка',
        'Морс клюквенный',
        'Блинчики с творогом и изюмом',
        'Торт Подворье',
        'Мороженое',
        'Эспрессо',
        'Американо',
        'Капучино',
        'Латте',
        'Фрапучино',
        'Аффогато (Мороженое, кофе, арахис, мед)',
        'Чай заварной в ассортименте',
        'Кола',
        'Спрайт',
        'Тоник',
        'Боржоми',
        'Мин вода газ/нега',
        'Сок в ассортименте',
        'Лимонад "Мятный Апельсин"',
        'Сейди Романс',
        'Смузи тропики',
        'Смузи "Санторини"',
        'Молочный коктейль в асс-те',
        'Тропик тоник',
        'Шмель',
        'Апрель б/а',
        'Апельсин',
        'Козел темное (0,45)',
        'Козел светлое (0,45)',
        'Балтика № 3 (0,5)',
        'Корона (0,33)',
        'Пиво безалкогольное (0,5)',
        'Пиво местное (светлое ) (0,5)',
        'Пиво местное ( темное ) (0,5)',
        'Голубая лагуна',
        'Пино-Колада',
        'Мохито',
        'Джин-тоник',
        'Куба-Либра',
        'Сангрия красная',
        'Сангрия белая',
        'Апероль Шпритц',
        'Шардоне де пуркарь',
        'Крикова Шардоне кружева',
        'Лунарди белое',
        'Квинт «Шардоне - Иршаи Оливер»',
        'Каберне де пуркарь',
        'Крикова Каберне кружева',
        'Лунарди красное',
        'Квинт «Саперави - Каберне Совиньон»',
        'Крикова Изабелла кружева',
        'Ламбруско белое',
        'Ламбруско розовое',
        'Рионда Гарда брют',
        'Крикова Мускат',
        'Крикова белое',
        'Крикова Лакрима Дулче',
        'CUVEE DE PURCARI BRUT',
        'Тирасполь',
        'Дивин «Mark» X. O. 10 лет',
        'Сюрпризный',
        'Нистру',
        'Финляндия',
        'Хортица',
        'Джемисон',
        'Джек Дениелс',
        'Ром белый',
        'Ром золотой',
        'Ром черный',
        'Текила сильвер',
        'Текила голд',
        "Gordon's",
        'Квас',
        'Швепс 200 мл',
        'Швепс 750 мл',
        'Фреш Апельсин',
        'Пицца Пепперони',
        'Пицца 4 сыра',
        'Пицца с курицей и грибами',
        'Бургер биф',
        'Бургер чикен',
        'Соус барбекю',
        'Соус чили',
        'Соус кетчуп',
        'Соус тартар',
        'Картофель фри',
        'Стрипсы куриные',
        'Гренки',
        'Цезарь с курицей',
        'Цезарь с креветкой',
        'Салат летний',
        'Чипсы в асс.',
        'Орешки в асс.',
    ];

    /**
     * Restaurant slug => WooCommerce domain.
     *
     * @var array<string, string>
     */
    private const WOOCOMMERCE_DOMAINS = [
        'kasta' => 'casta.md',
        'toskana' => 'casta.md',
        'mafiia' => 'casta.md',
        'dzordziia' => 'gruzia.md',
        'dzordziia-bendery' => 'gruzia.md',
    ];

    /**
     * Restaurant slugs that use the hardcoded Santorini menu.
     *
     * @var array<int, string>
     */
    private const SANTORINI_SLUGS = [
        'santorini-podvore',
        'santorini-bassein',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::WOOCOMMERCE_DOMAINS as $slug => $domain) {
            DB::table('restaurants')->where('slug', $slug)->update(['woocommerce_domain' => $domain]);
        }

        $now = now();

        foreach (self::SANTORINI_SLUGS as $slug) {
            $restaurant = DB::table('restaurants')->where('slug', $slug)->first();

            if ($restaurant === null) {
                continue;
            }

            $rows = [];

            foreach (array_values(self::SANTORINI_MENU) as $index => $name) {
                $rows[] = [
                    'restaurant_id' => $restaurant->id,
                    'name' => $name,
                    'sort_order' => $index + 1,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('menu_items')->insert($rows);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::SANTORINI_SLUGS as $slug) {
            $restaurant = DB::table('restaurants')->where('slug', $slug)->first();

            if ($restaurant === null) {
                continue;
            }

            DB::table('menu_items')->where('restaurant_id', $restaurant->id)->delete();
        }

        foreach (self::WOOCOMMERCE_DOMAINS as $slug => $domain) {
            DB::table('restaurants')->where('slug', $slug)->update(['woocommerce_domain' => null]);
        }
    }
};
