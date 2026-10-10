# Convention

## General AI & Dev working rule

- Read the current code and project conventions first; follow project naming and data-structure rules.
- Write clear, readable code with meaningful names; avoid long lines and deeply nested logic.
- Avoid `//` and other non-hoverable comments inside function bodies. If code or a variable genuinely needs explanation, use a DocBlock above it so the explanation is available on hover, and tell the developer why before adding it.
- When considering extracting code into a separate function, check whether the function exceeds 25 lines and whether the code has a clear, separate responsibility; do not extract solely to avoid explaining a short but complex value.
- Preserve working code and user-written comments. Do not rewrite unrelated structure; ask for the current file when required.
- Analyze code and evidence before proposing a fix. Do not guess; state uncertainty and ask for missing files, state, or logs.
- Use targeted logs with file, function, and variable context to trace changed data; explain what the evidence shows and validate before claiming success.
- Update related UI, state, and JSON data together when the requested change requires it.
- Stay within the requested task. Be direct, avoid repeated summaries, and ask only when something is unclear.

## M-project Convention

- ยึด convention ที่กำหนดไว้สำหรับ M-project เป็นหลัก ก่อน convention ทั่วไปของ framework เช่น Laravel; ตรวจรูปแบบที่มีอยู่ก่อนเพิ่มโครงสร้างใหม่ และถามเมื่อยังไม่ชัดเจน
- M Data codes such as d , u , cd , cu , cud , uf are an explicit exception: retain the established short codes consistently because they are shared Project M vocabulary. Do not invent new unexplained abbreviations.
- M-project เป็นเครื่องมือสำหรับช่วยพัฒนาโปรเจกต์อื่น จึงควรรักษา logic ให้สั้นและเรียบง่าย แยก service, storage หรือ abstraction เพิ่มเฉพาะเมื่อมีความจำเป็นชัดเจน และ refactor โดยคงพฤติกรรมเดิม
- จัดโครงสร้างตาม phase และวางส่วนที่เกี่ยวข้องไว้ใกล้กันเท่าที่เหมาะสม: backend ใช้ `app\Constant` / `App\Constant` และ frontend อยู่ใน `Components`; ไฟล์ frontend/backend ที่ทำงานร่วมกันใช้ phase เดียวกัน

### M-project Example : 
- `Sync_Manager`: ให้วาง backend logic ใน `app\Constant\3_M_Sync_Manager_Service.php` (`App\Constant\Sync_Manager_Service`) และ frontend ใน `Components\3_M_Sync_Manager.jsx` แทนการย้ายไปตามโครงสร้าง Laravel โดยอัตโนมัติ หากแยกส่วนหรือเปลี่ยนชื่อ ต้องรักษาการเชื่อมต่อและพฤติกรรมเดิมของโมดูล

## CLEAN CODE
* ลด nesting ให้เหลือระดับเดียวเป็นหลัก; ถ้าจำเป็นจริง ๆ ยอมได้ไม่เกิน 2 ชั้น
* แตกเป็นฟังก์ชันย่อยเมื่อช่วยให้ nesting ตื้นลงและอ่านง่ายขึ้น
* ตั้งชื่อตัวแปรให้สื่อความหมายและค่าที่เก็บ หลีกเลี่ยงชื่อกำกวม เช่น `$res`, `$data`, `$tmp`; ตัวอย่าง: `$scanned_Projects`, `$existing_Paths`, `$target_Info`
* ใช้ underscore แยกคำเมื่อช่วยให้อ่านง่าย และรักษารูปแบบตัวพิมพ์ผสมของโปรเจกต์ เช่น `fieldname_UPPERCASE`, `d_name_UPPERCASE`; ตัวย่อที่มีความหมายให้ใช้ตัวพิมพ์ใหญ่
* ชื่อสั้น (`k`, `v`, `type`, `key`) ใช้ได้เฉพาะขอบเขตเล็กมากประมาณ 1–2 บรรทัด; ใช้ชื่ออธิบายเมื่อค่าถูกใช้หลายบรรทัดหรือหลายจุด
* คอมเมนต์นอก function body ให้ใช้ภาษาอังกฤษและมีเท่าที่จำเป็น; คำอธิบายภาษาไทยให้ไว้ในแชต
* รักษาโค้ดเดิมและพฤติกรรมทั้งหมดเมื่อ refactor; ไม่ลบตรรกะหรือเปลี่ยนโครงสร้างที่ไม่เกี่ยวข้อง
* ถ้าข้อมูลหรือขอบเขตไม่ชัด ให้ถามก่อน อย่าเดา
* Keep functions focused and readable on one screen: usually 10–20 lines, up to 25 when justified. Refactor longer functions instead of adding comments to divide them.

## DocBlock

- ใส่ DocBlock เหนือทุกฟังก์ชัน ระบุ purpose, ชนิดและความหมายของ `@param`, และ `@return` เพื่อให้อ่านได้จาก hover
- แสดง data shape จริงใน `@param` และ `@return` ด้วยตัวอย่างหลายบรรทัดเมื่อเป็นข้อมูลซ้อนกัน; ตัวอย่าง `@return` ต้องต่อยอดจาก input และแสดงสิ่งที่เปลี่ยน
- ใส่ JSDoc/DocBlock เหนือ variable เมื่อช่วยอธิบายข้อมูลหรือการใช้งานที่ชื่อเพียงอย่างเดียวสื่อไม่ได้; ใช้ตัวอย่างจริงและหลีกเลี่ยงคำอธิบายซ้ำ
- เมื่อต้องย้ายฟังก์ชันเดิม ให้คง DocBlock ต้นฉบับไว้ก่อน เว้นแต่ตกลงกันให้ปรับ

### DocBlock - Example

```php
    /**
     * 1. Scan a parent directory for Laravel projects, then merge saved targets from config.
     * 2. Save the merged list back to config
     * @param  \Illuminate\Http\Request  $request  Optional input "base", e.g. "C:/Users/o/.vscode/react"
     * @return \Illuminate\Http\JsonResponse  e.g.
     * * {
     * *    "success":true,
     * *    "base":"C:/Users/o/.vscode/react",
     * *    "projects":
     * *    [
     * *        {
     * *            "name":"ecommerce",
     * *            "root_path":"C:/Users/o/.vscode/react/ecommerce"
     * *        },
     * *        {
     * *            "name":"m-project",
     * *            "root_path":"C:/Users/o/.vscode/react/m-project"
     * *         }
     * *    ]
     * * }
     */
    public function scanTargets(Request $request)
    {
```

```javascript
/**
 * @param f_s_Class_Array = e.g.
 * * ['f::NAME', 'f::IMAGE', 's::EMAIL', 'f::IS_ACTIVE']
 * @param TABLENAME = e.g. PRODUCTS , ORDERS , USERS
 * * DB Table with DB Column in it
 * * {
 * *     "_comment": "\/M_JSON\/Entities.json",
 * *  "entities": {
 * *         "t::ORDERS": [
 * *             "f::ORDER_NR",
 * *             "f::PRODUCT_ID",
 * *             "f::USER_ID",
 * *             "f::QUANTITY",
 * *             "f::CONFIRM_ORDER"
 * *         ],
 * *     }
 * * }
 */
export default function EntityField({ f_s_Class_Array, TABLENAME }) {
```

###  DocBlock - Example : good comment example on top of function or variable e.g.

```javascript
/**
 * make checkboxes for UI rules (READONLY, DISABLED, etc.)
 * @param {*} UI_options e.g. ["READONLY"]
 * @param {*} ALL_UI_options e.g. ["READONLY", "DISABLED", ...]
 */
export function UI_Rule({ UI_options, ALL_UI_options, field_data }) {
```

### DocBlock - Example : readable hover text

Break long descriptions into short lines or separate items so they remain readable in the hover popup. Keep related text together; avoid blank lines that split one description into separate hover sections.

```javascript
    /**
     * * setChecked_CD
     * * prepare_new_M_value_for_Update
     * * set_M_value
     * * update JSON
     */
    async function set_D_CD_Actions(option, event) {

    /**
     * * clone of M_value
     * * M_value = m_data or app_data or entities
     * * , when clicked on Main Tab APP_DATA, M_DATA, ENTITIES
     */
    const new_M_value = { ...old_M_value };
    
/**
 * Get options for dropdown or checkbox based on Class Name
 * * Example usage:
 * * M_Class_Name: "entities" -> uses metadata.m_data
 * * M_Class_Name: "f"        -> uses metadata.app_data
 * *
 * * else if (activeTab === "m_data")
 * * here for Class s Definition in m_data
 * *
 * * M_Class_Name === "s" some entitiy (DB_Table) has Class s fields
 */
export function use_M_Option() {
```

### DocBlock - Example Indentation for nested data examples in DocBlocks

* แสดงโครงสร้าง array, JSON หรือข้อมูลซ้อนกันด้วยการเยื้องให้เห็นทุกระดับ
* เมื่อข้อมูลซ้อนลึกขึ้นหนึ่งระดับ ให้เพิ่ม `*` อีกหนึ่งตัวในบรรทัดของระดับนั้น
* บรรทัด item และ field ภายใน array ต้องเยื้องลึกกว่าบรรทัดวงเล็บที่ครอบมันหนึ่งระดับ
* วงเล็บเปิดและวงเล็บปิดของแต่ละระดับต้องเยื้องอยู่แนวเดียวกัน และใส่ comma ตามรูปแบบข้อมูลจริง
```php
     * * [
     * * * [
     * * * * "name" => "ecommerce",
     * * * * "root_path" => "C:/Users/o/.vscode/react/ecommerce"
     * * * ],
     * * ]
```

## Class and File 
- ในไฟล์ PHP ให้มีหนึ่งคลาสหลัก เพื่อให้ค้นหาและเข้าใจความรับผิดชอบของไฟล์ได้ง่าย
- พยายามให้หนึ่งคลาสมีขนาดไม่เกินประมาณ 200–300 บรรทัด หากยาวเกินไป ให้พิจารณาย้ายฟังก์ชันไปไฟล์แยกโดยแยกหน้าที่ให้ชัดเจน
- ใช้ prefix ตัวเลข เช่น `0_`, `1_`, `2_`, `3_` เพื่อบอก phase/ลำดับการทำงานของไฟล์
- ส่วน `_M_` ในชื่อไฟล์ เช่น `3_M_Sync_Manager...` ระบุว่า logic นั้นจัดการ M-value/metadata ของ Project M โดยตรง ไม่ใช่แค่ไฟล์ที่อยู่ในโปรเจกต์ M
- วางไฟล์ backend ของ M-project แบบ flat ไว้ใน `app\Constant` และใช้ namespace `App\Constant`
- ตั้งชื่อไฟล์ให้กระชับและมี phase prefix เช่น `3_M_Sync_Manager_Run_Command.php`
- ตั้งชื่อคลาสให้สัมพันธ์กับชื่อไฟล์ โดยใช้ underscore เช่น `class Sync_Manager_Run_Command extends Command`
- วางไฟล์ frontend และ backend ที่เกี่ยวข้องไว้ใน phase เดียวกัน เช่น `3_M_Sync_Manager.jsx` กับ `3_M_Sync_Manager_Run_Command.php`
- เมื่อย้ายหรือเปลี่ยนชื่อไฟล์/คลาส ให้อัปเดต Laravel registration และจุดอ้างอิงที่จำเป็น เพื่อให้พฤติกรรมเดิมยังทำงานได้
- Scan the whole class for repeated hard-coded values, including strings, numbers, and booleans. Define them as class constants.
- If a value must be derived at runtime, create one private method to return it and call that method wherever the value is needed.
- Apply this cleanup within the class first. Preserve existing logic and DocBlocks.

```php
class TargetController
{
    private const CONFIG_PATH = 'Constant/3_M-Config.json';

    private function get_Config_Path(): string
    {
        return app_path(self::CONFIG_PATH);
    }

    public function scanTargets()
    {
        if (file_exists($this->get_Config_Path())) {
            $config_Data = json_decode(
                file_get_contents($this->get_Config_Path()),
                true
            );
        }

        file_put_contents(
            $this->get_Config_Path(),
            json_encode($config_Data, JSON_PRETTY_PRINT)
        );
    }
}
```
## Log Convention
- เก็บ log ไว้ที่เดียวในโฟลเดอร์ภายนอกแอปตาม convention; ห้ามทำสำเนาหรือสะสม log ในโปรเจกต์/ฐานข้อมูล และลบไฟล์ชั่วคราวเมื่อ reset หรือเริ่ม run ใหม่`
