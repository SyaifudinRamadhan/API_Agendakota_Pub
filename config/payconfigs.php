<?php 
return [
    "methods" => [
        "e-wallet" => [
            "011" => ["ID_DANA", "DANA", 0.015],
            "012" => ["ID_LINKAJA", "LinkAja", 0.027],
            "013" => ["ID_SHOPEEPAY", "ShopeePay", 0.04],
            "014" => ["ID_OVO", "OVO", 0.0273],
            "015" => ["ID_JENIUSPAY", "JeniusPay", 0.02]
        ],
        "qris" => [
            "021" => ["ID_DANA", "DANA", 0.007],
            "022" => ["ID_LINKAJA", "LinkAja", 0.007],
        ],
        "VA" => [
            "031" => ["BCA", "BCA", 4000], 
            "032" => ["BNI", "BNI", 4000], 
            "033" => ["BRI", "BRI", 4000], 
            "034" => ["BJB", "BJB", 4000], 
            "035" => ["BSI", "BSI", 4000], 
            "036" => ["BNC", "BNC", 4000], 
            "037" => ["CIMB", "CIMB", 4000], 
            "038" => ["DBS", "DBS", 4000], 
            "039" => ["MANDIRI", "MANDIRI", 4000], 
            "040" => ["PERMATA", "PERMATA", 4000],
            "041" => ["SAHABAT_SAMPOERNA", "SAHABAT SAMPOERNA", 4000]
        ]
        ],
    "payout_fee" => 4000
];
?>