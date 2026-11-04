<?php
/**
 * LocalBusiness の型（schema.org のサブタイプ）を扱うクラス。
 */
defined( 'ABSPATH' ) || exit;
class AVC_AIS_LocalBusinessTypes {
	/**
	 * 有効な型がないときに出力する型。
	 */
	const DEFAULT_TYPE = 'LocalBusiness';

	/**
	 * 以前のウィザードの選択肢にあった、schema.org に存在しない型と、対応する型。
	 */
	const RENAMED = array(
		'Cafe'                => 'CafeOrCoffeeShop',
		'FitnessCenter'       => 'ExerciseGym',
		'HealthAndBeautySpa'  => 'DaySpa',
		'HomeAndConstruction' => 'HomeAndConstructionBusiness',
	);

	/**
	 * LocalBusiness とそのサブタイプ（schema.org 30.1 の型一覧から生成）。
	 */
	const TYPES = array(
		'AccountingService',
		'AdultEntertainment',
		'AmusementPark',
		'AnimalShelter',
		'ArchiveOrganization',
		'ArtGallery',
		'Attorney',
		'Audiology',
		'AutoBodyShop',
		'AutoDealer',
		'AutoPartsStore',
		'AutoRental',
		'AutoRepair',
		'AutoWash',
		'AutomatedTeller',
		'AutomotiveBusiness',
		'Bakery',
		'BankOrCreditUnion',
		'BarOrPub',
		'BeautySalon',
		'BedAndBreakfast',
		'BikeStore',
		'BookStore',
		'BowlingAlley',
		'Brewery',
		'CafeOrCoffeeShop',
		'Campground',
		'Casino',
		'ChildCare',
		'ClothingStore',
		'ComedyClub',
		'CommunityHealth',
		'ComputerStore',
		'ConvenienceStore',
		'CovidTestingFacility',
		'DaySpa',
		'Dentist',
		'DepartmentStore',
		'Dermatology',
		'DietNutrition',
		'Distillery',
		'DryCleaningOrLaundry',
		'Electrician',
		'ElectronicsStore',
		'Emergency',
		'EmergencyService',
		'EmploymentAgency',
		'EntertainmentBusiness',
		'ExerciseGym',
		'FastFoodRestaurant',
		'FinancialService',
		'FireStation',
		'Florist',
		'FoodEstablishment',
		'FurnitureStore',
		'GardenStore',
		'GasStation',
		'GeneralContractor',
		'Geriatric',
		'GolfCourse',
		'GovernmentOffice',
		'GroceryStore',
		'Gynecologic',
		'HVACBusiness',
		'HairSalon',
		'HardwareStore',
		'HealthAndBeautyBusiness',
		'HealthClub',
		'HobbyShop',
		'HomeAndConstructionBusiness',
		'HomeGoodsStore',
		'Hospital',
		'Hostel',
		'Hotel',
		'HousePainter',
		'IceCreamShop',
		'IndividualPhysician',
		'InsuranceAgency',
		'InternetCafe',
		'JewelryStore',
		'LegalService',
		'Library',
		'LiquorStore',
		'LocalBusiness',
		'Locksmith',
		'LodgingBusiness',
		'MedicalBusiness',
		'MedicalClinic',
		'MensClothingStore',
		'Midwifery',
		'MobilePhoneStore',
		'Motel',
		'MotorcycleDealer',
		'MotorcycleRepair',
		'MovieRentalStore',
		'MovieTheater',
		'MovingCompany',
		'MusicStore',
		'NailSalon',
		'NightClub',
		'Notary',
		'Nursing',
		'Obstetric',
		'OfficeEquipmentStore',
		'Oncologic',
		'Ophthalmology',
		'Optician',
		'Optometric',
		'Otolaryngologic',
		'OutletStore',
		'PawnShop',
		'Pediatric',
		'PetStore',
		'Pharmacy',
		'Physician',
		'PhysiciansOffice',
		'Physiotherapy',
		'PlasticSurgery',
		'Plumber',
		'Podiatric',
		'PoliceStation',
		'PostOffice',
		'PrimaryCare',
		'ProfessionalService',
		'Psychiatric',
		'PublicHealth',
		'PublicSwimmingPool',
		'RadioStation',
		'RealEstateAgent',
		'RecyclingCenter',
		'Resort',
		'Restaurant',
		'RoofingContractor',
		'SelfStorage',
		'ShoeStore',
		'ShoppingCenter',
		'SkiResort',
		'SportingGoodsStore',
		'SportsActivityLocation',
		'SportsClub',
		'StadiumOrArena',
		'Store',
		'TattooParlor',
		'TelevisionStation',
		'TennisComplex',
		'TireShop',
		'TouristInformationCenter',
		'ToyStore',
		'TravelAgency',
		'VacationRental',
		'WholesaleStore',
		'Winery',
	);

	/**
	 * 選択できる型を返す。
	 *
	 * @return string[] 型.
	 */
	public static function all() {
		/**
		 * 選択できる LocalBusiness の型を絞り込む。
		 *
		 * schema.org に追加された型や、LocalBusiness 以外の型を使うときに追加する。
		 *
		 * @param string[] $types 型.
		 */
		return (array) apply_filters( 'avc_ais_local_business_types', self::TYPES );
	}

	/**
	 * よく使う型と、画面に表示する名前を返す。
	 *
	 * @return array<string,string> 型をキーとする名前.
	 */
	public static function common_choices() {
		$choices = array(
			'LocalBusiness'               => __( 'General Local Business', 'aivec-ai-search-schema' ),
			'Restaurant'                  => __( 'Restaurant', 'aivec-ai-search-schema' ),
			'CafeOrCoffeeShop'            => __( 'Cafe', 'aivec-ai-search-schema' ),
			'BarOrPub'                    => __( 'Bar or Pub', 'aivec-ai-search-schema' ),
			'Bakery'                      => __( 'Bakery', 'aivec-ai-search-schema' ),
			'Store'                       => __( 'Store / Retail', 'aivec-ai-search-schema' ),
			'BeautySalon'                 => __( 'Beauty Salon', 'aivec-ai-search-schema' ),
			'HairSalon'                   => __( 'Hair Salon', 'aivec-ai-search-schema' ),
			'DaySpa'                      => __( 'Spa', 'aivec-ai-search-schema' ),
			'Dentist'                     => __( 'Dentist', 'aivec-ai-search-schema' ),
			'Physician'                   => __( 'Doctor / Physician', 'aivec-ai-search-schema' ),
			'Hospital'                    => __( 'Hospital', 'aivec-ai-search-schema' ),
			'Pharmacy'                    => __( 'Pharmacy', 'aivec-ai-search-schema' ),
			'LegalService'                => __( 'Legal Service', 'aivec-ai-search-schema' ),
			'AccountingService'           => __( 'Accounting Service', 'aivec-ai-search-schema' ),
			'RealEstateAgent'             => __( 'Real Estate Agent', 'aivec-ai-search-schema' ),
			'AutoRepair'                  => __( 'Auto Repair', 'aivec-ai-search-schema' ),
			'Hotel'                       => __( 'Hotel', 'aivec-ai-search-schema' ),
			'ExerciseGym'                 => __( 'Fitness Center / Gym', 'aivec-ai-search-schema' ),
			'HomeAndConstructionBusiness' => __( 'Home & Construction Service', 'aivec-ai-search-schema' ),
		);

		return array_intersect_key( $choices, array_flip( self::all() ) );
	}

	/**
	 * 以前の選択肢の型を、対応する型へ読み替える。
	 *
	 * @param mixed $type 型.
	 * @return mixed 読み替えた型。文字列でなければそのまま返す.
	 */
	public static function rename( $type ) {
		return is_string( $type ) ? ( self::RENAMED[ $type ] ?? $type ) : $type;
	}

	/**
	 * 選択できる型かを判定する。
	 *
	 * @param mixed $type 型.
	 * @return bool 選択できれば true.
	 */
	public static function is_valid( $type ) {
		return null !== self::find( $type );
	}

	/**
	 * JSON-LD に出力する型を返す。
	 *
	 * 以前は自由入力だったので、保存済みの値は大文字小文字や空白が違うことがある。対応する型が
	 * なければ、schema.org に存在しない型を出力しないよう LocalBusiness にする。
	 * フィルターで配列にしたサイトでは複数の型をそのまま出力する。
	 *
	 * @param mixed $type 型.
	 * @return string|string[] 型.
	 */
	public static function to_schema_type( $type ) {
		if ( ! is_array( $type ) ) {
			return self::find( $type ) ?? self::DEFAULT_TYPE;
		}

		$types = array_values( array_unique( array_filter( array_map( array( self::class, 'find' ), $type ) ) ) );
		if ( empty( $types ) ) {
			return self::DEFAULT_TYPE;
		}

		return 1 === count( $types ) ? $types[0] : $types;
	}

	/**
	 * 選択肢で選択済みにする型を返す。
	 *
	 * @param mixed $type 型.
	 * @return string 型.
	 */
	public static function to_selected_choice( $type ) {
		$schema_type = self::to_schema_type( $type );

		return is_array( $schema_type ) ? $schema_type[0] : $schema_type;
	}

	/**
	 * 表記の違いを吸収して、対応する型を探す。
	 *
	 * @param mixed $type 型.
	 * @return string|null 対応する型。なければ null.
	 */
	private static function find( $type ) {
		if ( ! is_string( $type ) ) {
			return null;
		}

		$key = static function ( $value ) {
			return strtolower( (string) preg_replace( '/\s+/', '', $value ) );
		};

		$types = self::all();

		return array_combine( array_map( $key, $types ), $types )[ $key( self::rename( $type ) ) ] ?? null;
	}
}
