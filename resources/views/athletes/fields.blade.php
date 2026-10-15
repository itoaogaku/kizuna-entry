<div class="row">
    <div class="field">
        <label for="last_name">姓</label>
        <input id="last_name" type="text" name="last_name" value="{{ old('last_name', $athlete->last_name) }}" placeholder="鈴木" required>
    </div>
    <div class="field">
        <label for="first_name">名</label>
        <input id="first_name" type="text" name="first_name" value="{{ old('first_name', $athlete->first_name) }}" placeholder="太郎" required>
    </div>
</div>
<div class="row">
    <div class="field">
        <label for="kana">フリガナ <span class="hint">（姓と名の間にスペース）</span></label>
        <input id="kana" type="text" name="kana" value="{{ old('kana', $athlete->kana) }}" placeholder="スズキ タロウ" required>
    </div>
    <div class="field">
        <label>性別</label>
        @foreach (\App\Models\Athlete::GENDERS as $value => $label)
            <label class="inline"><input type="radio" name="gender" value="{{ $value }}" @checked(old('gender', $athlete->gender) === $value)> {{ $label }}</label>
        @endforeach
    </div>
</div>
<div class="row">
    <div class="field">
        <label for="category">種別</label>
        <select id="category" name="category">
            <option value="">―</option>
            @foreach (\App\Models\Athlete::CATEGORIES as $category)
                <option value="{{ $category }}" @selected(old('category', $athlete->category) === $category)>{{ $category }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label for="grade">学年 <span class="hint">（学生の場合）</span></label>
        <select id="grade" name="grade">
            <option value="">―</option>
            @for ($g = 1; $g <= 6; $g++)
                <option value="{{ $g }}" @selected((string) old('grade', $athlete->grade) === (string) $g)>{{ $g }}年</option>
            @endfor
        </select>
    </div>
    <div class="field">
        <label for="association">所属陸協</label>
        <select id="association" name="association">
            <option value="">―</option>
            @foreach (\App\Models\Athlete::ASSOCIATIONS as $association)
                <option value="{{ $association }}" @selected(old('association', $athlete->association) === $association)>{{ $association }}</option>
            @endforeach
        </select>
    </div>
</div>
<div class="row">
    <div class="field">
        <label for="birthdate">生年月日 <span class="hint">（任意）</span></label>
        <input id="birthdate" type="date" name="birthdate" value="{{ old('birthdate', $athlete->birthdate?->format('Y-m-d')) }}">
    </div>
    <div class="field">
        <label for="jaaf_id">陸連登録番号 <span class="hint">（任意）</span></label>
        <input id="jaaf_id" type="text" name="jaaf_id" value="{{ old('jaaf_id', $athlete->jaaf_id) }}">
    </div>
</div>
