<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\DiningTable;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::forceCreate([
            'name' => 'Admin',
            'email' => 'admin@trattoria.test',
            'password' => bcrypt('password'),
        ]);

        foreach ($this->menu() as $i => $cat) {
            $category = Category::create([
                'name' => $cat['name'],
                'subtitle' => $cat['subtitle'],
                'translations' => $cat['translations'],
                'sort_order' => $i,
            ]);

            foreach ($cat['items'] as $j => $item) {
                $category->items()->create([
                    'name' => $item['name'],
                    'description' => $item['en'],
                    'translations' => [
                        'it' => ['description' => $item['it']],
                        'pt' => ['description' => $item['pt']],
                    ],
                    'price_cents' => $item['price'],
                    'image' => $item['image'] ?? null,
                    'is_vegetarian' => $item['veg'] ?? false,
                    'is_spicy' => $item['spicy'] ?? false,
                    'allergens' => $item['allergens'] ?? [],
                    'options' => $item['options'] ?? null,
                    'sort_order' => $j,
                ]);
            }
        }

        foreach (range(1, 8) as $n) {
            DiningTable::create(['number' => $n]);
        }
    }

    private function pizzaOptions(): array
    {
        return [
            ['name' => 'Size', 'type' => 'single', 'required' => true, 'choices' => [
                ['name' => 'Regular (32cm)', 'price_cents' => 0],
                ['name' => 'Large (40cm)', 'price_cents' => 400],
            ]],
            ['name' => 'Extras', 'type' => 'multiple', 'required' => false, 'choices' => [
                ['name' => 'Extra mozzarella', 'price_cents' => 150],
                ['name' => 'Mushrooms', 'price_cents' => 100],
                ['name' => 'Spicy salame', 'price_cents' => 150],
                ['name' => 'Fresh rocket', 'price_cents' => 100],
            ]],
        ];
    }

    private function pastaOptions(): array
    {
        return [
            ['name' => 'Pasta type', 'type' => 'single', 'required' => true, 'choices' => [
                ['name' => 'Classic', 'price_cents' => 0],
                ['name' => 'Gluten-free', 'price_cents' => 200],
            ]],
            ['name' => 'Extras', 'type' => 'multiple', 'required' => false, 'choices' => [
                ['name' => 'Extra Parmigiano', 'price_cents' => 100],
                ['name' => 'Chilli oil', 'price_cents' => 50],
            ]],
        ];
    }

    private function menu(): array
    {
        return [
            [
                'name' => 'Starters', 'subtitle' => 'Antipasti',
                'translations' => [
                    'it' => ['name' => 'Antipasti', 'subtitle' => 'Per iniziare'],
                    'pt' => ['name' => 'Entradas', 'subtitle' => 'Antipasti'],
                ],
                'items' => [
                    ['name' => 'Bruschetta al Pomodoro', 'price' => 650, 'veg' => true, 'image' => 'bruschetta.webp', 'allergens' => ['gluten'],
                        'en' => 'Toasted ciabatta, ripe tomatoes, garlic, basil and extra virgin olive oil.',
                        'it' => 'Ciabatta tostata, pomodori maturi, aglio, basilico e olio extravergine d\'oliva.',
                        'pt' => 'Ciabatta tostada, tomates maduros, alho, manjericão e azeite virgem extra.'],
                    ['name' => 'Caprese', 'price' => 950, 'veg' => true, 'image' => 'caprese.webp', 'allergens' => ['dairy'],
                        'en' => 'Buffalo mozzarella, vine tomatoes and fresh basil with a drizzle of olive oil.',
                        'it' => 'Mozzarella di bufala, pomodori a grappolo e basilico fresco con un filo d\'olio.',
                        'pt' => 'Mozzarella de búfala, tomate de cacho e manjericão fresco com um fio de azeite.'],
                    ['name' => 'Carpaccio di Manzo', 'price' => 1250, 'image' => 'carpaccio.webp', 'allergens' => ['dairy'],
                        'en' => 'Thinly sliced raw beef, rocket, shaved Parmigiano and lemon.',
                        'it' => 'Fettine sottili di manzo crudo, rucola, scaglie di Parmigiano e limone.',
                        'pt' => 'Fatias finas de carne de vaca crua, rúcula, lascas de Parmigiano e limão.'],
                    ['name' => 'Arancini', 'price' => 800, 'image' => 'arancini.webp', 'allergens' => ['gluten', 'dairy', 'eggs'],
                        'en' => 'Crispy saffron risotto balls filled with mozzarella and ragù.',
                        'it' => 'Crocchette croccanti di risotto allo zafferano ripiene di mozzarella e ragù.',
                        'pt' => 'Croquetes crocantes de risotto de açafrão recheados com mozzarella e ragù.'],
                    ['name' => 'Prosciutto e Melone', 'price' => 1100, 'image' => 'prosciutto-melone.webp', 'allergens' => [],
                        'en' => 'Aged Parma ham served with sweet cantaloupe melon.',
                        'it' => 'Prosciutto di Parma stagionato servito con dolce melone cantalupo.',
                        'pt' => 'Presunto de Parma curado servido com melão cantalupo doce.'],
                ],
            ],
            [
                'name' => 'Pizzas', 'subtitle' => 'Dal forno a legna',
                'translations' => [
                    'it' => ['name' => 'Pizze', 'subtitle' => 'Dal forno a legna'],
                    'pt' => ['name' => 'Pizzas', 'subtitle' => 'Do forno a lenha'],
                ],
                'items' => [
                    ['name' => 'Margherita', 'price' => 900, 'veg' => true, 'image' => 'margherita.webp', 'allergens' => ['gluten', 'dairy'], 'options' => $this->pizzaOptions(),
                        'en' => 'San Marzano tomato, fior di latte mozzarella and fresh basil.',
                        'it' => 'Pomodoro San Marzano, mozzarella fior di latte e basilico fresco.',
                        'pt' => 'Tomate San Marzano, mozzarella fior di latte e manjericão fresco.'],
                    ['name' => 'Diavola', 'price' => 1150, 'spicy' => true, 'image' => 'diavola.webp', 'allergens' => ['gluten', 'dairy'], 'options' => $this->pizzaOptions(),
                        'en' => 'Tomato, mozzarella and spicy salame piccante.',
                        'it' => 'Pomodoro, mozzarella e salame piccante.',
                        'pt' => 'Tomate, mozzarella e salame picante.'],
                    ['name' => 'Quattro Formaggi', 'price' => 1300, 'veg' => true, 'image' => 'quattro-formaggi.webp', 'allergens' => ['gluten', 'dairy'], 'options' => $this->pizzaOptions(),
                        'en' => 'Mozzarella, gorgonzola, fontina and Parmigiano.',
                        'it' => 'Mozzarella, gorgonzola, fontina e Parmigiano.',
                        'pt' => 'Mozzarella, gorgonzola, fontina e Parmigiano.'],
                    ['name' => 'Prosciutto e Funghi', 'price' => 1250, 'image' => 'prosciutto-funghi.webp', 'allergens' => ['gluten', 'dairy'], 'options' => $this->pizzaOptions(),
                        'en' => 'Tomato, mozzarella, cooked ham and mushrooms.',
                        'it' => 'Pomodoro, mozzarella, prosciutto cotto e funghi.',
                        'pt' => 'Tomate, mozzarella, fiambre e cogumelos.'],
                    ['name' => 'Capricciosa', 'price' => 1350, 'image' => 'capricciosa.webp', 'allergens' => ['gluten', 'dairy'], 'options' => $this->pizzaOptions(),
                        'en' => 'Tomato, mozzarella, ham, artichokes, olives and mushrooms.',
                        'it' => 'Pomodoro, mozzarella, prosciutto, carciofi, olive e funghi.',
                        'pt' => 'Tomate, mozzarella, fiambre, alcachofras, azeitonas e cogumelos.'],
                ],
            ],
            [
                'name' => 'Pasta', 'subtitle' => 'Fatta in casa',
                'translations' => [
                    'it' => ['name' => 'Pasta', 'subtitle' => 'Fatta in casa'],
                    'pt' => ['name' => 'Massas', 'subtitle' => 'Feitas em casa'],
                ],
                'items' => [
                    ['name' => 'Spaghetti alla Carbonara', 'price' => 1300, 'image' => 'carbonara.webp', 'allergens' => ['gluten', 'eggs', 'dairy'], 'options' => $this->pastaOptions(),
                        'en' => 'Guanciale, egg yolk, Pecorino Romano and black pepper.',
                        'it' => 'Guanciale, tuorlo d\'uovo, Pecorino Romano e pepe nero.',
                        'pt' => 'Guanciale, gema de ovo, Pecorino Romano e pimenta preta.'],
                    ['name' => 'Lasagna della Nonna', 'price' => 1400, 'image' => 'lasagna.webp', 'allergens' => ['gluten', 'eggs', 'dairy', 'celery'],
                        'en' => 'Layers of fresh pasta, slow-cooked ragù and béchamel.',
                        'it' => 'Strati di pasta fresca, ragù cotto lentamente e besciamella.',
                        'pt' => 'Camadas de massa fresca, ragù cozinhado lentamente e bechamel.'],
                    ['name' => 'Penne all\'Arrabbiata', 'price' => 1100, 'veg' => true, 'spicy' => true, 'image' => 'arrabbiata.webp', 'allergens' => ['gluten'], 'options' => $this->pastaOptions(),
                        'en' => 'Penne in a spicy tomato, garlic and chilli sauce.',
                        'it' => 'Penne in salsa piccante di pomodoro, aglio e peperoncino.',
                        'pt' => 'Penne em molho picante de tomate, alho e piri-piri.'],
                    ['name' => 'Tagliatelle al Ragù', 'price' => 1350, 'image' => 'tagliatelle-ragu.webp', 'allergens' => ['gluten', 'eggs', 'celery'], 'options' => $this->pastaOptions(),
                        'en' => 'Egg tagliatelle with traditional Bolognese meat sauce.',
                        'it' => 'Tagliatelle all\'uovo con il tradizionale ragù alla bolognese.',
                        'pt' => 'Tagliatelle de ovo com o tradicional ragù à bolonhesa.'],
                    ['name' => 'Risotto ai Funghi Porcini', 'price' => 1500, 'veg' => true, 'image' => 'risotto-porcini.webp', 'allergens' => ['dairy', 'celery', 'sulphites'],
                        'en' => 'Creamy Arborio rice with porcini mushrooms and Parmigiano.',
                        'it' => 'Riso Arborio cremoso con funghi porcini e Parmigiano.',
                        'pt' => 'Arroz Arborio cremoso com cogumelos porcini e Parmigiano.'],
                    ['name' => 'Gnocchi al Pesto', 'price' => 1250, 'veg' => true, 'image' => 'gnocchi-pesto.webp', 'allergens' => ['gluten', 'dairy', 'nuts'],
                        'en' => 'Potato gnocchi with Genovese basil pesto and pine nuts.',
                        'it' => 'Gnocchi di patate con pesto genovese al basilico e pinoli.',
                        'pt' => 'Gnocchi de batata com pesto genovês de manjericão e pinhões.'],
                ],
            ],
            [
                'name' => 'Main Courses', 'subtitle' => 'Secondi piatti',
                'translations' => [
                    'it' => ['name' => 'Secondi', 'subtitle' => 'Dalla cucina'],
                    'pt' => ['name' => 'Pratos principais', 'subtitle' => 'Secondi piatti'],
                ],
                'items' => [
                    ['name' => 'Saltimbocca alla Romana', 'price' => 2100, 'image' => 'saltimbocca.webp', 'allergens' => ['dairy', 'sulphites'],
                        'en' => 'Veal escalope with sage and prosciutto in white wine butter.',
                        'it' => 'Scaloppina di vitello con salvia e prosciutto al burro e vino bianco.',
                        'pt' => 'Escalope de vitela com salva e presunto em manteiga de vinho branco.'],
                    ['name' => 'Pollo alla Cacciatora', 'price' => 1800, 'image' => 'cacciatora.webp', 'allergens' => ['celery', 'sulphites'],
                        'en' => 'Braised chicken with tomatoes, peppers, olives and herbs.',
                        'it' => 'Pollo brasato con pomodori, peperoni, olive ed erbe aromatiche.',
                        'pt' => 'Frango estufado com tomate, pimentos, azeitonas e ervas aromáticas.'],
                    ['name' => 'Branzino al Forno', 'price' => 2300, 'image' => 'branzino.webp', 'allergens' => ['fish'],
                        'en' => 'Oven-roasted sea bass with lemon, capers and potatoes.',
                        'it' => 'Branzino al forno con limone, capperi e patate.',
                        'pt' => 'Robalo assado no forno com limão, alcaparras e batatas.'],
                    ['name' => 'Melanzane alla Parmigiana', 'price' => 1500, 'veg' => true, 'image' => 'parmigiana.webp', 'allergens' => ['dairy'],
                        'en' => 'Baked aubergine layered with tomato, mozzarella and Parmigiano.',
                        'it' => 'Melanzane al forno a strati con pomodoro, mozzarella e Parmigiano.',
                        'pt' => 'Beringela assada em camadas com tomate, mozzarella e Parmigiano.'],
                ],
            ],
            [
                'name' => 'Sides', 'subtitle' => 'Contorni',
                'translations' => [
                    'it' => ['name' => 'Contorni', 'subtitle' => 'Per accompagnare'],
                    'pt' => ['name' => 'Acompanhamentos', 'subtitle' => 'Contorni'],
                ],
                'items' => [
                    ['name' => 'Insalata Mista', 'price' => 550, 'veg' => true, 'image' => 'insalata-mista.webp', 'allergens' => ['sulphites'],
                        'en' => 'Mixed leaves, cherry tomatoes, cucumber and balsamic dressing.',
                        'it' => 'Insalata mista, pomodorini, cetriolo e condimento balsamico.',
                        'pt' => 'Salada mista, tomate cereja, pepino e molho balsâmico.'],
                    ['name' => 'Patate al Forno', 'price' => 500, 'veg' => true, 'image' => 'patate-forno.webp', 'allergens' => [],
                        'en' => 'Rosemary roasted potatoes.',
                        'it' => 'Patate arrosto al rosmarino.',
                        'pt' => 'Batatas assadas com alecrim.'],
                    ['name' => 'Spinaci all\'Aglio', 'price' => 550, 'veg' => true, 'image' => 'spinaci.webp', 'allergens' => [],
                        'en' => 'Sautéed spinach with garlic and olive oil.',
                        'it' => 'Spinaci saltati con aglio e olio d\'oliva.',
                        'pt' => 'Espinafres salteados com alho e azeite.'],
                ],
            ],
            [
                'name' => 'Desserts', 'subtitle' => 'Dolci',
                'translations' => [
                    'it' => ['name' => 'Dolci', 'subtitle' => 'Per finire in dolcezza'],
                    'pt' => ['name' => 'Sobremesas', 'subtitle' => 'Dolci'],
                ],
                'items' => [
                    ['name' => 'Tiramisù', 'price' => 700, 'veg' => true, 'image' => 'tiramisu.webp', 'allergens' => ['gluten', 'dairy', 'eggs'],
                        'en' => 'Mascarpone cream, espresso-soaked savoiardi and cocoa.',
                        'it' => 'Crema al mascarpone, savoiardi inzuppati nell\'espresso e cacao.',
                        'pt' => 'Creme de mascarpone, savoiardi embebidos em espresso e cacau.'],
                    ['name' => 'Panna Cotta', 'price' => 650, 'veg' => true, 'image' => 'panna-cotta.webp', 'allergens' => ['dairy'],
                        'en' => 'Vanilla cream with a wild berry coulis.',
                        'it' => 'Crema alla vaniglia con coulis di frutti di bosco.',
                        'pt' => 'Creme de baunilha com coulis de frutos silvestres.'],
                    ['name' => 'Cannoli Siciliani', 'price' => 700, 'veg' => true, 'image' => 'cannoli.webp', 'allergens' => ['gluten', 'dairy', 'nuts'],
                        'en' => 'Crisp pastry shells filled with sweet ricotta and pistachio.',
                        'it' => 'Croccanti cialde di pasta ripiene di ricotta dolce e pistacchio.',
                        'pt' => 'Cascas de massa crocantes recheadas com ricotta doce e pistácio.'],
                    ['name' => 'Affogato al Caffè', 'price' => 600, 'veg' => true, 'image' => 'affogato.webp', 'allergens' => ['dairy'],
                        'en' => 'Vanilla gelato drowned in a shot of hot espresso.',
                        'it' => 'Gelato alla vaniglia affogato in un espresso caldo.',
                        'pt' => 'Gelado de baunilha afogado num espresso quente.'],
                ],
            ],
            [
                'name' => 'Drinks', 'subtitle' => 'Bevande',
                'translations' => [
                    'it' => ['name' => 'Bevande', 'subtitle' => 'Da bere'],
                    'pt' => ['name' => 'Bebidas', 'subtitle' => 'Bevande'],
                ],
                'items' => [
                    ['name' => 'Acqua Naturale / Frizzante', 'price' => 350, 'veg' => true, 'image' => 'acqua.webp', 'allergens' => [],
                        'options' => [['name' => 'Type', 'type' => 'single', 'required' => true, 'choices' => [
                            ['name' => 'Still', 'price_cents' => 0], ['name' => 'Sparkling', 'price_cents' => 0]]]],
                        'en' => 'Still or sparkling mineral water, 750ml.',
                        'it' => 'Acqua minerale naturale o frizzante, 750ml.',
                        'pt' => 'Água mineral sem gás ou com gás, 750ml.'],
                    ['name' => 'Coca-Cola', 'price' => 300, 'veg' => true, 'image' => 'cola.webp', 'allergens' => [],
                        'options' => [['name' => 'Size', 'type' => 'single', 'required' => true, 'choices' => [
                            ['name' => '33cl', 'price_cents' => 0], ['name' => '50cl', 'price_cents' => 100]]]],
                        'en' => 'Served ice cold.',
                        'it' => 'Servita ghiacciata.',
                        'pt' => 'Servida bem gelada.'],
                    ['name' => 'Aperol Spritz', 'price' => 850, 'veg' => true, 'image' => 'spritz.webp', 'allergens' => ['sulphites'],
                        'en' => 'Aperol, prosecco and soda with an orange slice.',
                        'it' => 'Aperol, prosecco e soda con una fetta d\'arancia.',
                        'pt' => 'Aperol, prosecco e soda com uma fatia de laranja.'],
                    ['name' => 'Vino Rosso della Casa', 'price' => 550, 'veg' => true, 'image' => 'vino-rosso.webp', 'allergens' => ['sulphites'],
                        'en' => 'House red wine, glass.',
                        'it' => 'Vino rosso della casa, al calice.',
                        'pt' => 'Vinho tinto da casa, copo.'],
                    ['name' => 'Vino Bianco della Casa', 'price' => 550, 'veg' => true, 'image' => 'vino-bianco.webp', 'allergens' => ['sulphites'],
                        'en' => 'House white wine, glass.',
                        'it' => 'Vino bianco della casa, al calice.',
                        'pt' => 'Vinho branco da casa, copo.'],
                    ['name' => 'Caffè Espresso', 'price' => 200, 'veg' => true, 'image' => 'espresso.webp', 'allergens' => [],
                        'options' => [['name' => 'Type', 'type' => 'single', 'required' => true, 'choices' => [
                            ['name' => 'Regular', 'price_cents' => 0], ['name' => 'Decaf', 'price_cents' => 0]]]],
                        'en' => 'Classic Italian espresso.',
                        'it' => 'Classico espresso italiano.',
                        'pt' => 'Clássico espresso italiano.'],
                ],
            ],
        ];
    }
}
