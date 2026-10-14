            <div class="row">
                <div class="field">
                    <label for="last_name">姓</label>
                    <input id="last_name" type="text" name="last_name" value="{{ old('last_name', $athlete->last_name) }}" required>
                </div>
                <div class="field">
                    <label for="first_name">名</label>
                    <input id="first_name" type="text" name="first_name" value="{{ old('first_name', $athlete->first_name) }}" required>
                </div>
            </div>
            <div class="row">
                <div class="field">
                    <label for="last_kana">セイ <span class="hint">（カタカナ）</span></label>
                    <input id="last_kana" type="text" name="last_kana" value="{{ old('last_kana', $athlete->last_kana) }}" required>
                </div>
                <div class="field">
                    <label for="first_kana">メイ <span class="hint">（カタカナ）</span></label>
                    <input id="first_kana" type="text" name="first_kana" value="{{ old('first_kana', $athlete->first_kana) }}" required>
                </div>
            </div>
            <div class="row">
                <div class="field">
                    <label>性別</label>
                    @foreach (\App\Models\Athlete::GENDERS as $value => $label)
                        <label class="inline"><input type="radio" name="gender" value="{{ $value }}" @checked(old('gender', $athlete->gender) === $value)> {{ $label }}</label>
                    @endforeach
                </div>
                <div class="field">
                    <label for="birthdate">生年月日</label>
                    <input id="birthdate" type="date" name="birthdate" value="{{ old('birthdate', $athlete->birthdate?->format('Y-m-d')) }}" required>
                </div>
            </div>
            <div class="row">
                <div class="field">
                    <label for="category">区分 <span class="hint">（例：中学・高校・大学・一般）</span></label>
                    <input id="category" type="text" name="category" value="{{ old('category', $athlete->category) }}">
                </div>
                <div class="field">
                    <label for="jaaf_id">陸連登録番号 <span class="hint">（任意）</span></label>
                    <input id="jaaf_id" type="text" name="jaaf_id" value="{{ old('jaaf_id', $athlete->jaaf_id) }}">
                </div>
            </div>
