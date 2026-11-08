import type { SeedType } from '@/data/kebun';

export type GrowthStage = {
    number: number;
    code: string;
    label: string;
    shortLabel: string;
    range: string;
    percent: number;
    image: string;
    imageAlt: string;
    credit?: string;
    description: string;
    indicators: string[];
    care: string;
    risk: string;
};

export type GrowthProfile = {
    commodity: SeedType;
    variety: string;
    cycleLabel: string;
    stageBasis: string;
    stages: GrowthStage[];
};

const jagungImages = {
    kecambah: '/images/farmer/jagung-berkecambah.jpg',
    vegetatif: '/images/farm_fields/jagung-vegetatif.jpg',
    pembungaan: '/images/farm_fields/jagung-pembungaan.jpg',
    pengisian: '/images/farm_fields/jagung-reproduktif.jpg',
    panen: '/images/farmer/jagung-panen.jpg',
};

const bisi18: GrowthProfile = {
    commodity: 'Jagung',
    variety: 'Hibrida BISI-18',
    cycleLabel: '100–105 HST (dataran rendah)',
    stageBasis: 'Skala VE–R6',
    stages: [
        {
            number: 1,
            code: 'VE',
            label: 'Kecambah',
            shortLabel: 'Kecambah',
            range: '0–7 HST',
            percent: 7,
            image: jagungImages.kecambah,
            imageAlt: 'Kecambah jagung hibrida BISI-18 menembus tanah',
            description:
                'Benih BISI-18 berkecambah sekitar 4–7 hari setelah tanam. Koleoptil menembus permukaan tanah dan daun pertama mulai membuka, sementara tanaman masih mengandalkan cadangan makanan benih.',
            indicators: [
                'Kecambah muncul seragam di sepanjang barisan tanam.',
                'Tanah lembap, tidak tergenang, dan tidak retak.',
                'Tidak ada bibit yang menguning atau membusuk.',
            ],
            care: 'Pastikan drainase bedengan lancar, lakukan penyulaman paling lambat 7 HST, dan jaga kelembapan tanah sekitar 60–70%.',
            risk: 'Genangan atau tanah yang mengeras dapat menggagalkan perkecambahan dan membuat populasi tidak merata.',
        },
        {
            number: 2,
            code: 'V1–V10',
            label: 'Vegetatif',
            shortLabel: 'Vegetatif',
            range: '8–45 HST',
            percent: 43,
            image: jagungImages.vegetatif,
            imageAlt: 'Tanaman jagung BISI-18 muda pada fase vegetatif',
            description:
                'Daun pertama hingga ketiga membuka penuh dan akar serabut aktif, lalu batang memanjang cepat dan daun melebar. Mulai V4–V10 tajuk menutup barisan dan kebutuhan nitrogen serta air meningkat tajam.',
            indicators: [
                'Daun 1–3 membuka penuh dengan warna hijau segar.',
                'Batang bertambah tinggi, ruas kokoh, dan tidak mudah rebah.',
                'Tajuk mulai menutup dan daun bawah tidak menguning dini.',
            ],
            care: 'Lakukan penyulaman paling lambat 7 HST, penyiangan, dan pemupukan susulan nitrogen pada V3–V6. Bumbun ringan dan jaga pengairan tetap terjadwal.',
            risk: 'Lalat bibit atau ulat daun pada V1–V3, gulma, serta kekurangan nitrogen membuat tanaman kerdil dan tongkol kecil.',
        },
        {
            number: 3,
            code: 'VT–R1',
            label: 'Pembungaan & penyerbukan',
            shortLabel: 'Pembungaan',
            range: '46–60 HST',
            percent: 57,
            image: jagungImages.pembungaan,
            imageAlt: 'Malai jagung BISI-18 saat pembungaan',
            credit: 'L. Abendroth, Iowa State University Extension',
            description:
                'Malai jantan muncul di pucuk dan rambut tongkol keluar dari kelopak. Ini awal fase reproduktif: serbuk sari harus mencapai rambut tongkol saat reseptif, sehingga fase ini paling sensitif terhadap stres air.',
            indicators: [
                'Malai kuning terbuka di ujung tanaman.',
                'Rambut tongkol keluar segar berwarna putih hingga kemerahan.',
                'Daun bendera masih hijau dan terbuka lebar.',
            ],
            care: 'Jaga air pada zona perakaran, hindari penyemprotan saat penyerbukan, dan pantau penggerek batang serta tongkol.',
            risk: 'Kekeringan atau suhu ekstrem saat silking membuat tongkol tidak terisi penuh.',
        },
        {
            number: 4,
            code: 'R2–R5',
            label: 'Reproduktif (pengisian biji)',
            shortLabel: 'Reproduktif',
            range: '61–95 HST',
            percent: 90,
            image: jagungImages.pengisian,
            imageAlt: 'Tongkol jagung BISI-18 saat pengisian biji',
            description:
                'Setelah penyerbukan, biji melewati fase blister (R2), susu (R3), pasta (R4), dan dent (R5). Tongkol memanjang penuh dan biji terus mengisi; kebutuhan air tetap tinggi sampai R5.',
            indicators: [
                'Tongkol membesar dan memanjang penuh.',
                'Biji berisi cairan lalu mengental; permukaan biji mulai berlekuk.',
                'Kelobot masih hijau dengan ujung mulai mengering.',
            ],
            care: 'Jaga pengairan hingga R5, hindari panen dini, dan pantau penggerek tongkol serta penyakit jamur.',
            risk: 'Kekeringan pada R2–R3 membuat biji keriput, sedangkan serangan penggerek tongkol menurunkan bobot dan mutu.',
        },
        {
            number: 5,
            code: 'R6',
            label: 'Masak fisiologis & panen',
            shortLabel: 'Panen',
            range: '96–105 HST',
            percent: 100,
            image: jagungImages.panen,
            imageAlt: 'Tanaman jagung BISI-18 mengering menjelang panen',
            description:
                'Biji mengeras dan lapisan hitam terbentuk di pangkal biji sebagai tanda masak fisiologis. Di dataran rendah BISI-18 siap panen pada 100–105 HST; di dataran tinggi masak fisiologis dapat mencapai sekitar 125 HST.',
            indicators: [
                'Lapisan hitam terlihat di pangkal biji.',
                'Kelobot mengering dan tongkol mudah dipetik.',
                'Biji kuning mengkilap dan keras.',
            ],
            care: 'Hentikan pengairan menjelang panen, panen saat kadar air 20–25%, lalu keringkan hingga kadar air 14%.',
            risk: 'Panen terlambat meningkatkan serangan jamur dan menurunkan mutu serta bobot jual.',
        },
    ],
};

const lamuru: GrowthProfile = {
    commodity: 'Jagung',
    variety: 'Komposit Lamuru',
    cycleLabel: '90–95 HST',
    stageBasis: 'Skala VE–R6',
    stages: [
        {
            number: 1,
            code: 'VE',
            label: 'Kecambah',
            shortLabel: 'Kecambah',
            range: '0–6 HST',
            percent: 6,
            image: jagungImages.kecambah,
            imageAlt: 'Kecambah jagung komposit Lamuru di tanah',
            description:
                'Benih Lamuru berkecambah sekitar 5–7 hari setelah tanam. Koleoptil muncul ke permukaan, lalu daun pertama membuka; populasi yang seragam penting untuk menjaga hasil dan seleksi benih berikutnya.',
            indicators: [
                'Kecambah keluar serempak dari benih yang ditanam.',
                'Permukaan tanah tidak retak dan tidak tergenang.',
                'Tidak ada bibit yang membusuk atau terserang jamur.',
            ],
            care: 'Siapkan bedengan dengan drainase baik, lakukan penyulaman cepat, dan hindari tanam terlalu dalam agar kecambah mudah muncul.',
            risk: 'Tanah padat, genangan, atau benih tersimpan terlalu lama dapat menurunkan daya tumbuh.',
        },
        {
            number: 2,
            code: 'V1–V10',
            label: 'Vegetatif',
            shortLabel: 'Vegetatif',
            range: '7–43 HST',
            percent: 45,
            image: jagungImages.vegetatif,
            imageAlt: 'Tanaman jagung Lamuru muda pada fase vegetatif',
            description:
                'Daun muda membuka dan akar mencengkeram tanah, lalu batang memanjang dengan daun yang lebih ramping. Lamuru toleran tanah marginal, tetapi pertumbuhan cepat tetap membutuhkan air dan hara yang cukup.',
            indicators: [
                'Daun bertambah panjang dengan warna hijau merata.',
                'Batang kokoh namun tetap lentur terhadap angin.',
                'Tajuk mulai menutup sehingga menekan gulma.',
            ],
            care: 'Bersihkan gulma, beri pupuk organik atau starter, lalu pemupukan susulan berimbang saat V4–V8. Bumbun dan jaga pengairan pada pertumbuhan cepat.',
            risk: 'Gulma, kekurangan air, dan pemupukan tidak seimbang menekan jumlah daun produktif serta ukuran tongkol.',
        },
        {
            number: 3,
            code: 'VT–R1',
            label: 'Pembungaan & penyerbukan',
            shortLabel: 'Pembungaan',
            range: '44–58 HST',
            percent: 61,
            image: jagungImages.pembungaan,
            imageAlt: 'Malai jagung Lamuru saat pembungaan',
            credit: 'L. Abendroth, Iowa State University Extension',
            description:
                'Malai dan rambut tongkol muncul. Lamuru adaptif di dataran rendah hingga menengah, namun tetap peka terhadap kekeringan saat penyerbukan berlangsung.',
            indicators: [
                'Malai jantan terbuka dan berwarna kekuningan.',
                'Rambut tongkol keluar segar dan tidak kering.',
                'Tanaman tidak menunjukkan gejala layu pada siang hari.',
            ],
            care: 'Jaga air pada zona perakaran, hindari gangguan saat penyerbukan, dan pantau hama penggerek batang.',
            risk: 'Stres air pada saat silking membuat tongkol kecil atau tidak terisi sempurna.',
        },
        {
            number: 4,
            code: 'R2–R5',
            label: 'Reproduktif (pengisian biji)',
            shortLabel: 'Reproduktif',
            range: '59–88 HST',
            percent: 93,
            image: jagungImages.pengisian,
            imageAlt: 'Tongkol jagung Lamuru saat pengisian biji',
            description:
                'Biji melewati fase blister hingga dent dan terus mengisi. Pada Lamuru, pengisian biji berlangsung sekitar empat minggu dan menentukan bobot panen serta mutu benih yang akan disimpan.',
            indicators: [
                'Tongkol membesar dengan biji yang semakin padat.',
                'Biji berubah dari berair menjadi mengental lalu berlekuk.',
                'Kelobot masih hijau dan tanaman belum rebah.',
            ],
            care: 'Cukupi air hingga R5, hindari panen dini, dan pantau penggerek tongkol serta jamur pada kelobot.',
            risk: 'Kekeringan saat pengisian membuat biji keriput dan menurunkan bobot serta viabilitas benih.',
        },
        {
            number: 5,
            code: 'R6',
            label: 'Masak fisiologis & panen',
            shortLabel: 'Panen',
            range: '89–95 HST',
            percent: 100,
            image: jagungImages.panen,
            imageAlt:
                'Tanaman jagung Lamuru siap panen dengan tongkol mengering',
            description:
                'Biji mengeras dan lapisan hitam terbentuk di pangkal biji. Lamuru matang sekitar 90–95 HST dan dapat dipanen sebagai jagung pipil; sebagian hasil dapat disimpan sebagai benih.',
            indicators: [
                'Kelobot mengering dan tongkol mudah dipetik.',
                'Biji keras dengan warna kuning merata.',
                'Daun dan batang mulai mengering secara alami.',
            ],
            care: 'Panen saat tanaman mengering, jagung segera dijemur, dan pisahkan tongkol sehat bila hasil akan disimpan sebagai benih.',
            risk: 'Panen terlambat meningkatkan serangan jamur dan menurunkan daya simpan benih.',
        },
    ],
};

const anjasmoro: GrowthProfile = {
    commodity: 'Kedelai',
    variety: 'Anjasmoro',
    cycleLabel: '85–90 HST',
    stageBasis: 'Skala VE–R8',
    stages: [
        {
            number: 1,
            code: 'VE',
            label: 'Kecambah',
            shortLabel: 'Kecambah',
            range: '0–5 HST',
            percent: 6,
            image: '/images/farmer/growth/kedelai-kecambah.jpg',
            imageAlt: 'Kecambah kedelai Anjasmoro di lahan',
            credit: 'Nirajan Pant, CC BY-SA 4.0, via Wikimedia Commons',
            description:
                'Benih Anjasmoro mulai berkecambah serentak. Kotiledon terangkat dan hipokotil memanjang; kelembapan tanah harus stabil agar akar pertama tidak rusak.',
            indicators: [
                'Kotiledon hijau terangkat di atas permukaan tanah.',
                'Hipokotil tegak dan tidak bengkok.',
                'Populasi kecambah relatif seragam.',
            ],
            care: 'Jaga drainase petak, jangan menggenangi bedengan, dan lakukan penyulaman benih yang tidak tumbuh sebelum 5 HST.',
            risk: 'Genangan dan tanah padat menghambat perkecambahan serta memicu busuk benih.',
        },
        {
            number: 2,
            code: 'VC–V6',
            label: 'Vegetatif',
            shortLabel: 'Vegetatif',
            range: '6–35 HST',
            percent: 39,
            image: '/images/farmer/growth/kedelai-vegetatif.jpg',
            imageAlt: 'Tanaman kedelai Anjasmoro pada fase vegetatif',
            description:
                'Daun trifoliat pertama dan kedua membuka, lalu cabang serta daun tumbuh cepat hingga tajuk menutup baris. Anjasmoro bertipe determinate, sehingga pembentukan cabang produktif terjadi pada fase ini.',
            indicators: [
                'Daun trifoliat membuka penuh dan berwarna hijau merata.',
                'Cabang baru muncul dari ketiak daun.',
                'Nodul akar aktif dan tanah terlihat lembap.',
            ],
            care: 'Lakukan penyiangan ringan, beri pupuk susulan NPK berimbang, dan jaga kelembapan tanah sebelum tajuk rapat.',
            risk: 'Gulma, kekurangan air, dan hara tidak seimbang menekan pembentukan cabang produktif.',
        },
        {
            number: 3,
            code: 'R1–R2',
            label: 'Pembungaan & penyerbukan',
            shortLabel: 'Pembungaan',
            range: '36–45 HST',
            percent: 50,
            image: '/images/farmer/growth/kedelai-pembungaan.jpg',
            imageAlt: 'Bunga ungu kedelai Anjasmoro di ketiak daun',
            credit: 'United Soybean Board, CC BY 2.0, via Wikimedia Commons',
            description:
                'Bunga ungu kecil muncul di ketiak daun, dari satu bunga terbuka (R1) hingga mekar penuh (R2). Kedelai menyerbuk sendiri saat bunga belum membuka, sehingga fase ini paling menentukan jumlah polong yang terbentuk.',
            indicators: [
                'Bunga ungu bermunculan di ketiak daun.',
                'Bunga mekar merata pada cabang utama dan cabang samping.',
                'Daun atas masih hijau dan tidak layu.',
            ],
            care: 'Jaga air tetap tersedia, hindari insektisida saat lebah aktif, dan pantau pengisap polong serta ulat penggerek.',
            risk: 'Kekeringan saat pembungaan membuat bunga rontok dan jumlah polong berkurang.',
        },
        {
            number: 4,
            code: 'R3–R6',
            label: 'Reproduktif (pembentukan & pengisian polong)',
            shortLabel: 'Reproduktif',
            range: '46–70 HST',
            percent: 78,
            image: '/images/farmer/growth/kedelai-reproduktif.jpg',
            imageAlt: 'Polong kedelai Anjasmoro saat pengisian biji',
            description:
                'Polong muda memanjang (R3), mencapai panjang penuh (R4), lalu biji mulai berisi (R5) hingga penuh (R6). Pada Anjasmoro, fase ini menentukan jumlah biji per polong dan bobot hasil.',
            indicators: [
                'Polong hijau memanjang hingga mendekati panjang penuh.',
                'Polong berisi 2–3 biji dan terasa padat.',
                'Daun atas masih hijau tanpa gejala layu.',
            ],
            care: 'Jaga pengairan, pantau pengisap polong dan karat daun, serta hindari panen dini saat biji belum penuh.',
            risk: 'Kekeringan dan serangan hama pengisap membuat polong hampa serta menurunkan bobot biji.',
        },
        {
            number: 5,
            code: 'R7–R8',
            label: 'Masak fisiologis & panen',
            shortLabel: 'Panen',
            range: '71–90 HST',
            percent: 100,
            image: '/images/farmer/growth/kedelai-panen.webp',
            imageAlt: 'Polong kedelai Anjasmoro terisi penuh menjelang panen',
            description:
                'Satu polong mulai matang (R7), lalu hampir seluruh polong matang (R8). Anjasmoro dipanen pada 85–90 HST saat 90–95% polong menguning kecokelatan dengan kadar air sekitar 13–14%.',
            indicators: [
                'Polong berubah dari hijau ke kuning kecokelatan.',
                'Daun bawah menguning dan rontok secara alami.',
                'Biji terasa keras saat polong ditekan.',
            ],
            care: 'Hentikan penyiraman menjelang panen, panen pada pagi hari, lalu keringkan dan sortir biji segera setelah perontokan.',
            risk: 'Panen terlambat membuat polong pecah, biji berkutu, dan kualitas menurun.',
        },
    ],
};

const grobogan: GrowthProfile = {
    commodity: 'Kedelai',
    variety: 'Grobogan',
    cycleLabel: '75–80 HST',
    stageBasis: 'Skala VE–R8',
    stages: [
        {
            number: 1,
            code: 'VE',
            label: 'Kecambah',
            shortLabel: 'Kecambah',
            range: '0–4 HST',
            percent: 5,
            image: '/images/farmer/growth/kedelai-kecambah.jpg',
            imageAlt: 'Kecambah kedelai Grobogan di lahan',
            credit: 'Nirajan Pant, CC BY-SA 4.0, via Wikimedia Commons',
            description:
                'Benih Grobogan berkecambah cepat, terutama pada awal musim hujan. Varietas lokal ini relatif adaptif di tanah marginal, tetapi perkecambahan tetap membutuhkan drainase yang baik.',
            indicators: [
                'Kecambah muncul cepat dan serempak.',
                'Kotiledon sehat, tidak berlubang atau berjamur.',
                'Tanah lembap dan tidak tergenang.',
            ],
            care: 'Gunakan benih sehat, tanam pada kedalaman 3–5 cm, dan pastikan saluran drainase tidak tersumbat.',
            risk: 'Genangan pada tanah berat dapat membuat benih membusuk sebelum tumbuh.',
        },
        {
            number: 2,
            code: 'VC–V6',
            label: 'Vegetatif',
            shortLabel: 'Vegetatif',
            range: '5–30 HST',
            percent: 38,
            image: '/images/farmer/growth/kedelai-vegetatif.jpg',
            imageAlt: 'Tanaman kedelai Grobogan pada fase vegetatif',
            description:
                'Daun trifoliat muda membuka, lalu percabangan padat dan tajuk cepat menutup. Ciri khas Grobogan adalah pertumbuhan vegetatif yang relatif kuat di lahan marginal dengan pemeliharaan sederhana.',
            indicators: [
                'Daun trifoliat hijau dan terbuka normal.',
                'Cabang samping tumbuh padat dan tajuk merata.',
                'Tanah tertutup tajuk sehingga gulma tertekan.',
            ],
            care: 'Lakukan penyiangan dini, pupuk susulan secukupnya, dan hindari nitrogen berlebihan yang memicu daun terlalu rimbun.',
            risk: 'Gulma yang dibiarkan dan kekeringan berkepanjangan menghambat pembentukan polong.',
        },
        {
            number: 3,
            code: 'R1–R2',
            label: 'Pembungaan & penyerbukan',
            shortLabel: 'Pembungaan',
            range: '31–40 HST',
            percent: 50,
            image: '/images/farmer/growth/kedelai-pembungaan.jpg',
            imageAlt: 'Bunga kedelai Grobogan berwarna ungu',
            credit: 'United Soybean Board, CC BY 2.0, via Wikimedia Commons',
            description:
                'Bunga mulai mekar serempak, dari satu bunga terbuka (R1) hingga mekar penuh (R2). Grobogan cukup toleran kekeringan, tetapi kebutuhan air tetap tinggi pada fase ini.',
            indicators: [
                'Bunga ungu muncul di ketiak daun.',
                'Bunga mekar merata pada cabang utama dan samping.',
                'Tanaman tidak menunjukkan gejala layu pagi hari.',
            ],
            care: 'Jaga pengairan, hindari gangguan pada perakaran, dan pantau hama pengisap polong serta penyakit karat daun.',
            risk: 'Stres air dan serangan hama pengisap dapat mengurangi jumlah polong isi.',
        },
        {
            number: 4,
            code: 'R3–R6',
            label: 'Reproduktif (pembentukan & pengisian polong)',
            shortLabel: 'Reproduktif',
            range: '41–65 HST',
            percent: 81,
            image: '/images/farmer/growth/kedelai-reproduktif.jpg',
            imageAlt: 'Polong kedelai Grobogan saat pengisian biji',
            description:
                'Polong muda memanjang (R3), mencapai panjang penuh (R4), lalu biji mulai berisi (R5) hingga penuh (R6). Grobogan dikenal berbiji besar, sehingga pengisian polong sangat menentukan bobot panen.',
            indicators: [
                'Polong hijau memanjang hingga mendekati panjang penuh.',
                'Polong berisi 2–3 biji besar dan padat.',
                'Daun atas masih hijau dan batang kokoh.',
            ],
            care: 'Jaga air pada musim kering, pantau pengisap polong serta karat daun, dan hindari panen sebelum biji penuh.',
            risk: 'Kekeringan dan serangan hama pengisap membuat polong hampa serta menurunkan mutu biji.',
        },
        {
            number: 5,
            code: 'R7–R8',
            label: 'Masak fisiologis & panen',
            shortLabel: 'Panen',
            range: '66–80 HST',
            percent: 100,
            image: '/images/farmer/growth/kedelai-panen.webp',
            imageAlt: 'Polong kedelai Grobogan terisi penuh menjelang panen',
            description:
                'Satu polong mulai matang (R7), lalu hampir seluruh polong matang (R8). Grobogan matang serempak sekitar 75–80 HST; panen dilakukan saat daun menguning dan polong mulai cokelat.',
            indicators: [
                'Polong berisi penuh dan mulai berubah warna.',
                'Daun menguning secara merata.',
                'Batang masih kokoh hingga panen.',
            ],
            care: 'Panen saat 90–95% polong matang, hindari penundaan, dan segera keringkan biji untuk menjaga kadar protein serta mutu.',
            risk: 'Panen terlambat saat musim hujan membuat polong pecah dan biji berjamur.',
        },
    ],
};

const odot: GrowthProfile = {
    commodity: 'Rumput',
    variety: 'Odot',
    cycleLabel: '46–60 HST per siklus panen',
    stageBasis: 'Siklus hijauan potong',
    stages: [
        {
            number: 1,
            code: 'Stek',
            label: 'Penanaman stek',
            shortLabel: 'Stek',
            range: '0–7 HST',
            percent: 12,
            image: '/images/farmer/growth/rumput-stek.jpg',
            imageAlt: 'Stek rumput Odot mulai mengeluarkan tunas',
            credit: 'Forest and Kim Starr, CC BY 3.0 US, via Wikimedia Commons',
            description:
                'Stek Odot 2–3 ruas ditanam dengan posisi miring atau tegak. Akar dan tunas baru muncul dari mata tunas; keberhasilan fase ini menentukan jumlah rumpun produktif.',
            indicators: [
                'Stek tertanam kokoh dan tidak mudah tercabut.',
                'Mata tunas mulai membengkak atau menghijau.',
                'Tanah lembap, tidak tergenang, dan bebas gulma.',
            ],
            care: 'Tanam stek pada kedalaman 5–10 cm, jaga kelembapan tanah, dan beri naungan ringan bila matahari terlalu terik.',
            risk: 'Stek kering atau tertimbun tanah dapat gagal bertunas dan mengurangi populasi rumpun.',
        },
        {
            number: 2,
            code: 'Tunas',
            label: 'Tunas awal',
            shortLabel: 'Tunas',
            range: '8–14 HST',
            percent: 23,
            image: '/images/farmer/growth/rumput-tunas.jpg',
            imageAlt: 'Tunas muda rumput Odot tumbuh dari pangkal rumpun',
            credit: 'Forest and Kim Starr, CC BY 3.0 US, via Wikimedia Commons',
            description:
                'Tunas baru keluar dari mata tunas dan daun pertama membuka. Rumpun masih kecil, sehingga persaingan gulma dan genangan harus dikendalikan.',
            indicators: [
                'Tunas hijau muncul dari beberapa mata stek.',
                'Daun pertama membuka dan tidak menguning.',
                'Tidak ada genangan di sekitar pangkal stek.',
            ],
            care: 'Siangi gulma di sekitar rumpun, perbaiki drainase, dan beri pupuk organik ringan bila pertumbuhan tunas lambat.',
            risk: 'Genangan membuat tunas muda membusuk, sedangkan gulma menutup cahaya.',
        },
        {
            number: 3,
            code: 'Anakan',
            label: 'Anakan aktif',
            shortLabel: 'Anakan',
            range: '15–30 HST',
            percent: 50,
            image: '/images/farm_fields/rumput.avif',
            imageAlt: 'Lahan rumput Odot dengan anakan yang mulai rapat',
            description:
                'Rumpun membentuk anakan baru dan daun melebar hingga tajuk mulai rapat. Fase ini menentukan kerapatan rumpun yang akan menentukan hasil hijauan.',
            indicators: [
                'Jumlah anakan bertambah dalam satu rumpun.',
                'Daun melebar dengan warna hijau segar.',
                'Tajuk mulai menutup permukaan tanah.',
            ],
            care: 'Beri pupuk nitrogen atau kompos, jaga pengairan, dan lakukan penyiangan sebelum anakan bertambah rapat.',
            risk: 'Gulma dan kekurangan hara menekan jumlah anakan serta kualitas daun.',
        },
        {
            number: 4,
            code: 'Veg',
            label: 'Pertumbuhan cepat',
            shortLabel: 'Vegetatif',
            range: '31–45 HST',
            percent: 75,
            image: '/images/farmer/growth/rumput-vegetatif.jpg',
            imageAlt: 'Rumpun rumput Odot tumbuh tinggi dan rimbun',
            credit: 'Forest and Kim Starr, CC BY 3.0 US, via Wikimedia Commons',
            description:
                'Batang dan daun memanjang cepat hingga tinggi 60–80 cm. Kebutuhan air dan nitrogen tinggi; panen sebaiknya belum dilakukan agar rumpun cukup kuat.',
            indicators: [
                'Tinggi tanaman bertambah cepat setiap minggu.',
                'Daun panjang, lebar, dan masih hijau segar.',
                'Rumpun tampak rimbun tanpa banyak batang kering.',
            ],
            care: 'Pastikan pengairan cukup, tambahkan pupuk nitrogen bila daun pucat, dan hindari pemotongan terlalu dini.',
            risk: 'Kekurangan air atau hara membuat daun menguning dan batang mengeras.',
        },
        {
            number: 5,
            code: 'Panen',
            label: 'Siap panen',
            shortLabel: 'Panen',
            range: '46–60 HST',
            percent: 100,
            image: '/images/farmer/growth/rumput-siap-panen.jpg',
            imageAlt: 'Rumpun rumput Odot rimbun siap dipotong',
            credit: 'Forest and Kim Starr, CC BY 3.0 US, via Wikimedia Commons',
            description:
                'Tajuk rimbun dengan daun hijau segar dan tinggi sekitar 80–100 cm. Odot siap dipotong pada 46–60 HST; setelah panen, ratoon berikutnya dapat dipanen setiap 40–45 hari.',
            indicators: [
                'Tajuk rimbun dan menutup rapat permukaan tanah.',
                'Sebagian besar daun masih hijau dan tidak mengering.',
                'Tinggi tanaman mencapai 80–100 cm.',
            ],
            care: 'Potong 10–15 cm dari permukaan tanah, sisakan minimal 2–3 mata tunas, lalu beri pupuk dan air untuk pertumbuhan ratoon.',
            risk: 'Pemotongan terlalu tinggi atau terlambat membuat hijauan berkayu dan menurunkan kualitas pakan.',
        },
    ],
};

const growthProfiles: Record<SeedType, GrowthProfile[]> = {
    Jagung: [bisi18, lamuru],
    Kedelai: [anjasmoro, grobogan],
    Rumput: [odot],
};

function normalizeVariety(value: string): string {
    return value
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, ' ')
        .trim();
}

export function getGrowthProfile(
    seedType: SeedType,
    variety?: string,
): GrowthProfile {
    const profiles = growthProfiles[seedType];
    const requested = variety ? normalizeVariety(variety) : '';

    if (requested) {
        const matched = profiles.find((profile) => {
            const name = normalizeVariety(profile.variety);
            return (
                name === requested ||
                name.includes(requested) ||
                requested.includes(name)
            );
        });

        if (matched) return matched;
    }

    return profiles[0];
}

export function getGrowthStageAtPercent(
    profile: GrowthProfile,
    growth: number,
): GrowthStage {
    const clamped = Math.min(100, Math.max(0, growth));

    return (
        profile.stages.find((stage) => clamped <= stage.percent) ??
        profile.stages[profile.stages.length - 1]
    );
}
