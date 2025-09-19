from pathlib import Path
path = Path(r"C:\xampp\htdocs\fluxvision_fin\api\infographie\CacheManager.php")
text = path.read_text(encoding='utf-8')
needle = "    private static $lastLookupMeta = null;\n\n    public function __construct"
if needle not in text:
    raise SystemExit('Needle not found')
insert = "    private static $lastLookupMeta = null;\n\n    private function buildCacheKey(string $category, array $params): string {\n        ksort($params);\n        return $category . ':' . md5(json_encode($params, JSON_UNESCAPED_UNICODE));\n    }\n\n    public function getCategoryTtl(string $category): int {\n        return $this->defaultTtl[$category] ?? 3600;\n    }\n\n    public static function getLastLookupMeta(): ?array {\n        return self::$lastLookupMeta;\n    }\n\n    public function __construct"
text = text.replace(needle, insert)
path.write_text(text, encoding='utf-8')
