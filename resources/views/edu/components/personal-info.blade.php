<h3>個人資料</h3>
<div class="table">
    <table class="table">
        <tr>
            <td>中文名</td>
            <td>{{ $student['billing_first_name'] ?? '' }}</td>
            <td>英文名</td>
            <td>{{ $student['billing_last_name'] ?? '' }}</td>
        </tr>
        <tr>
            <td>出生日期</td>
            <td>{{ $student['billing_birthdate'] ?? '' }}</td>
            <td>年齡</td>
            <td>{{ $calStudentAge }}</td>
        </tr>
        <tr>
            <td>學校/公司</td>
            <td>{{ $student['billing_school'] ?? '' }}</td>
            <td>地址</td>
            <td>{{ $student['billing_address_1'] ?? '' }}</td>
        </tr>
        <tr>
            <td>電話</td>
            <td>{{ $student['billing_phone'] ?? '' }}</td>
            <td>郵箱</td>
            <td>{{ $student['billing_email'] ?? '' }}</td>
        </tr>
        <tr>
            <td>緊急聯絡人</td>
            <td>{{ $student['billing_contactname'] ?? '' }}</td>
            <td>緊急聯絡人電話</td>
            <td>{{ $student['billing_contactphone'] ?? '' }}</td>
        </tr>
        <tr hidden>
            <td>終止續費</td>
            <td>
                <input type="radio"
                       name="no_renew"
                       value="1"
                        @checked(!empty($note['no_renew']))> 是
            </td>
        </tr>
    </table>
</div>