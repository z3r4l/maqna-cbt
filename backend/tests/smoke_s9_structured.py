"""Structured question lifecycle on the synthetic development database."""
import json
import http.cookiejar
import io
import struct
import subprocess
import time
import urllib.error
import urllib.request
import uuid
import zipfile
import zlib
from datetime import datetime, timedelta, timezone
from openpyxl import Workbook

ROOT = 'http://127.0.0.1:8081/api/v1'
DEVICE = 's9-fixture-device-1234567890'

def session():
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

def expect(label, client, method, path, status, payload=None, device=None):
    headers = {'Accept': 'application/json'}
    if device:
        headers['X-Attempt-Device'] = device
    if method != 'GET':
        csrf = expect('csrf', client, 'GET', '/auth/csrf', 200)
        headers[csrf['header']] = csrf['token']
    if payload is not None:
        headers['Content-Type'] = 'application/json'
    call = urllib.request.Request(ROOT + path, method=method,
                                  data=json.dumps(payload).encode() if payload is not None else None, headers=headers)
    try:
        response = client.open(call)
    except urllib.error.HTTPError as error:
        response = error
    body = json.loads(response.read())
    assert response.status == status, (label, response.status, body)
    if label != 'csrf':
        print(f'{label}: HTTP {response.status}')
    return body.get('data', body)

def upload_keys(client, filename, content, status):
    boundary = 'maqna' + str(time.time_ns())
    raw = (f'--{boundary}\r\nContent-Disposition: form-data; name="file"; filename="{filename}"\r\n'
           'Content-Type: application/octet-stream\r\n\r\n').encode() + content + f'\r\n--{boundary}--\r\n'.encode()
    csrf = expect('csrf', client, 'GET', '/auth/csrf', 200)
    call = urllib.request.Request(ROOT + '/questions/import/keys/preview', method='POST', data=raw,
                                  headers={csrf['header']: csrf['token'], 'Content-Type': 'multipart/form-data; boundary=' + boundary})
    try:
        response = client.open(call)
    except urllib.error.HTTPError as error:
        response = error
    body = json.loads(response.read())
    assert response.status == status, (response.status, body)
    return body.get('data', body)

def upload_question_media(client, content, status):
    boundary = 'maqna-media-' + str(time.time_ns())
    raw = (f'--{boundary}\r\nContent-Disposition: form-data; name="file"; filename="tiny.png"\r\n'
           'Content-Type: image/png\r\n\r\n').encode() + content + f'\r\n--{boundary}--\r\n'.encode()
    csrf = expect('csrf', client, 'GET', '/auth/csrf', 200)
    call = urllib.request.Request(ROOT + '/questions/media', method='POST', data=raw,
                                  headers={csrf['header']: csrf['token'], 'Content-Type': 'multipart/form-data; boundary=' + boundary})
    try:
        response = client.open(call)
    except urllib.error.HTTPError as error:
        response = error
    body = json.loads(response.read())
    assert response.status == status, (response.status, body)
    if status == 201:
        print(f'question image upload: HTTP {response.status}')
    return body.get('data', body)

def upload_package(client, kind, content, mode, status):
    boundary = 'maqna' + str(time.time_ns())
    raw = (f'--{boundary}\r\nContent-Disposition: form-data; name="mode"\r\n\r\n{mode}\r\n'
           f'--{boundary}\r\nContent-Disposition: form-data; name="file"; filename="questions.{kind}"\r\n'
           'Content-Type: application/octet-stream\r\n\r\n').encode() + content + f'\r\n--{boundary}--\r\n'.encode()
    csrf = expect('csrf', client, 'GET', '/auth/csrf', 200)
    call = urllib.request.Request(ROOT + f'/questions/import/{kind}/preview', method='POST', data=raw,
                                  headers={csrf['header']: csrf['token'], 'Content-Type': 'multipart/form-data; boundary=' + boundary})
    try:
        response = client.open(call)
    except urllib.error.HTTPError as error:
        response = error
    body = json.loads(response.read())
    assert response.status == status, (response.status, body)
    return body.get('data', body)

def tiny_png():
    def chunk(name, data):
        return struct.pack('>I', len(data)) + name + data + struct.pack('>I', zlib.crc32(name + data) & 0xffffffff)
    return b'\x89PNG\r\n\x1a\n' + chunk(b'IHDR', struct.pack('>IIBBBBB', 1, 1, 8, 6, 0, 0, 0)) + chunk(b'IDAT', zlib.compress(b'\x00\xff\x00\x00\xff')) + chunk(b'IEND', b'')

teacher, student, admin = session(), session(), session()
for client, identity in [(teacher, "teacher_demo"), (student, "student_demo"), (admin, "admin_demo")]:
    expect(identity + " login", client, "POST", "/auth/login", 200, {"identity": identity, "password": "FixtureOnly123!"})
masters = expect("masters", teacher, "GET", "/question-masters", 200)
math = next(row for row in masters["subjects"] if row["name"] == "Matematika")
assignment = next(row for row in expect("teaching", teacher, "GET", "/teacher/assignments", 200)
                  if int(row["subject_id"]) == int(math["id"]))

specs = [
    ("multi_mcq", [{"body": "A", "isCorrect": True}, {"body": "B", "isCorrect": True}, {"body": "C", "isCorrect": False}], None),
    ("true_false", [{"body": "P", "isCorrect": True}, {"body": "Q", "isCorrect": False}], None),
    ("matching", [{"body": "X", "matchChoiceIndex": 0}, {"body": "Y", "matchChoiceIndex": 1}],
     [{"body": "Satu"}, {"body": "Dua"}, {"body": "Pengecoh"}]),
]
ids = []
for kind, options, matching_choices in specs:
    row = expect("create " + kind, teacher, "POST", "/questions", 201,
                 {"subjectId": int(math["id"]), "body": "S9 " + kind + " " + str(time.time_ns()),
                  "questionType": kind, "weight": 1, "options": options,
                  **({"matchingChoices": matching_choices} if matching_choices is not None else {}), "status": "published"})
    ids.append(int(row["id"]))
    assert row["question_type"] == kind
expect("student cannot read keys", student, "GET", f"/questions/{ids[0]}", 403)
expect("invalid multi key", teacher, "POST", "/questions", 422,
       {"subjectId": int(math["id"]), "body": "Invalid", "questionType": "multi_mcq",
        "options": [{"body": "A", "isCorrect": True}, {"body": "B", "isCorrect": False}], "status": "published"})
expect("invalid matching duplicate", teacher, "POST", "/questions", 422,
       {"subjectId": int(math["id"]), "body": "Invalid", "questionType": "matching",
        "options": [{"body": "A", "matchBody": "same"}, {"body": "B", "matchBody": "same"}], "status": "published"})
expect("invalid matching reused key", teacher, "POST", "/questions", 422,
       {"subjectId": int(math["id"]), "body": "Invalid repeated key", "questionType": "matching",
        "matchingChoices": [{"body": "A"}, {"body": "B"}, {"body": "Distractor"}],
        "options": [{"body": "1", "matchChoiceIndex": 0}, {"body": "2", "matchChoiceIndex": 0}], "status": "published"})
expect("invalid matching missing key", teacher, "POST", "/questions", 422,
       {"subjectId": int(math["id"]), "body": "Invalid missing key", "questionType": "matching",
        "matchingChoices": [{"body": "A"}, {"body": "B"}],
        "options": [{"body": "1", "matchChoiceIndex": 0}, {"body": "2"}], "status": "published"})
expect("publish mcq", teacher, "POST", "/questions", 201,
       {"subjectId": int(math["id"]), "body": "MCQ publication " + str(time.time_ns()), "questionType": "mcq", "weight": 1,
        "options": [{"body": "A", "isCorrect": True}, {"body": "B", "isCorrect": False}], "status": "published"})
expect("publish essay", teacher, "POST", "/questions", 201,
       {"subjectId": int(math["id"]), "body": "Essay publication " + str(time.time_ns()), "questionType": "essay", "weight": 1,
        "rubric": "Nilai berdasarkan ketepatan", "options": [], "status": "published"})
temporary = upload_question_media(teacher, tiny_png(), 201)
expect("temporary image hidden from another user", student, "GET", temporary["src"].removeprefix('/api/v1'), 403)
temporary_option = upload_question_media(teacher, tiny_png(), 201)
rich_question = expect("create question with temporary image", teacher, "POST", "/questions", 201,
                       {"subjectId": int(math["id"]), "body": f'<p>Rich stimulus</p><p><img src="{temporary["src"]}" alt="fixture"></p>',
                        "questionType": "mcq", "weight": 1,
                        "options": [{"body": f'<p><img src="{temporary_option["src"]}" alt="option fixture"></p>Benar', "isCorrect": True}, {"body": "Salah", "isCorrect": False}], "status": "published"})
assert temporary["src"] not in rich_question["body"] and "/api/v1/question-assets/" in rich_question["body"]
assert temporary_option["src"] not in rich_question["options"][0]["body"]
asset_ids = [int(value) for value in __import__('re').findall(r'/api/v1/question-assets/(\d+)', rich_question["body"] + rich_question["options"][0]["body"])]
assert len(asset_ids) == 2
asset_id = asset_ids[0]
for media_id in asset_ids:
    asset_response = teacher.open(urllib.request.Request(ROOT + f"/question-assets/{media_id}", headers={"Accept": "image/*"}))
    assert asset_response.status == 200 and asset_response.headers.get_content_type() == "image/png"
print("question and option images available to owner: HTTP 200")
ids.append(int(rich_question["id"]))
expect("zero weight denied", teacher, "POST", "/questions", 422,
       {"subjectId": int(math["id"]), "body": "Invalid weight", "questionType": "mcq", "weight": 0,
        "options": [{"body": "A", "isCorrect": True}, {"body": "B", "isCorrect": False}], "status": "published"})
now = datetime.now(timezone.utc)
exam = expect("create exam", teacher, "POST", "/exams", 201, {
    "title": "S9 structured " + str(time.time_ns()), "subjectId": int(math["id"]),
    "semesterId": int(assignment["semester_id"]), "classIds": [int(assignment["class_group_id"])],
    "questionIds": ids, "startAt": (now-timedelta(minutes=1)).strftime("%Y-%m-%dT%H:%M:%SZ"),
    "endAt": (now+timedelta(hours=1)).strftime("%Y-%m-%dT%H:%M:%SZ"),
    "durationMinutes": 30, "passingGrade": 70, "maxAttempts": 1, "resultPolicy": "first",
    "shuffleQuestions": True, "shuffleOptions": True})
draft_preview = expect('draft student preview', teacher, 'GET', f"/exams/{exam['id']}/preview", 200)
assert len(draft_preview['items']) == 4 and 'is_correct' not in json.dumps(draft_preview).lower()
assert all('id' in item and 'choices' in item for item in draft_preview['items'])
assert any(item['type'] == 'matching' and item['choices'] for item in draft_preview['items'])
assert not any(key in json.dumps(draft_preview).lower() for key in ['rubric', 'explanation', 'match_choice_id'])
preview_asset = next(asset for item in draft_preview['items'] for asset in item['assets'])
expect('exam-scoped preview image', teacher, 'GET', f"/exams/{exam['id']}/preview/assets/{preview_asset['id']}", 200)
assert int(expect('preview leaves attempts unchanged', teacher, 'GET', f"/exams/{exam['id']}", 200)['attemptCount']) == 0
expect('student preview denied', student, 'GET', f"/exams/{exam['id']}/preview", 403)
expect("publish exam", teacher, "POST", f"/exams/{exam['id']}/publish", 200)
published_preview = expect('published student preview', teacher, 'GET', f"/exams/{exam['id']}/preview", 200)
assert 'is_correct' not in json.dumps(published_preview).lower() and 'rubric' not in json.dumps(published_preview).lower()
attempt = expect("start", student, "POST", f"/student/exams/{exam['id']}/start", 200, device=DEVICE)
assert {item["type"] for item in attempt["items"]} == {"mcq", "multi_mcq", "true_false", "matching"}
assert "is_correct" not in json.dumps(attempt).lower() and "match_body" not in json.dumps(attempt).lower()
image_item = next(item for item in attempt["items"] if item["type"] == "mcq")
assert all(any(int(asset["id"]) == media_id for asset in image_item["assets"]) for media_id in asset_ids)
for media_id in asset_ids:
    private_response = student.open(urllib.request.Request(ROOT + f"/student/attempts/{attempt['id']}/assets/{media_id}", headers={"Accept": "image/*", "X-Attempt-Device": DEVICE}))
    assert private_response.status == 200 and private_response.headers.get_content_type() == "image/png"
    expect("student cannot access question asset outside attempt", student, "GET", f"/question-assets/{media_id}", 403)
print("question and option images available in student snapshot: HTTP 200")
for item in attempt["items"]:
    options = item["options"]
    selected = None
    if item["type"] == "multi_mcq":
        answer = [int(options[0]["id"]), int(options[1]["id"])]
        expect("duplicate answer denied", student, "PATCH", f"/student/attempts/{attempt['id']}/items/{item['id']}", 422,
               {"expectedRevision": 0, "selectedOptionId": None, "answer": answer + answer[:1],
                "marked": False, "mutationId": str(uuid.uuid4())}, DEVICE)
        # Use source option bodies to select the two correct answers, independent of display shuffle.
        answer = [int(option["id"]) for option in options if option["body"] in ("A", "B")]
    elif item["type"] == "true_false":
        answer = {str(option["id"]): option["body"] == "P" for option in options}
    elif item["type"] == "mcq":
        selected = next(int(option["id"]) for option in rich_question["options"] if int(option["is_correct"]) == 1)
        answer = None
    else:
        assert len(item["choices"]) == 3 and all("is_correct" not in choice for choice in item["choices"])
        choice_by_body = {choice["body"]: int(choice["id"]) for choice in item["choices"]}
        answer = {str(option["id"]): choice_by_body["Satu" if option["body"] == "X" else "Dua"] for option in options}
    save_payload = {"expectedRevision": 0, "selectedOptionId": selected, "marked": False, "mutationId": str(uuid.uuid4())}
    if item["type"] != "mcq":
        save_payload["answer"] = answer
    expect("save " + item["type"], student, "PATCH", f"/student/attempts/{attempt['id']}/items/{item['id']}", 200, save_payload, DEVICE)
resumed = expect("resume", student, "GET", f"/student/attempts/{attempt['id']}", 200, device=DEVICE)
assert all(item["answer"] or item["selectedOptionId"] is not None for item in resumed["items"])
expect("submit", student, "POST", f"/student/attempts/{attempt['id']}/submit", 200, device=DEVICE)
score = subprocess.check_output(["mysql", "-N", "-u", "root", "maqna_cbt_dev", "-e",
                                 f"SELECT score FROM attempt_results WHERE attempt_id={attempt['id']}"], text=True).strip()
assert float(score) == 100.0, score
subprocess.check_call(["mysql", "-u", "root", "maqna_cbt_dev", "-e",
                       f"UPDATE exams SET end_at=UTC_TIMESTAMP()-INTERVAL 1 SECOND WHERE id={exam['id']}"])
expect("manual release", teacher, "POST", f"/exams/{exam['id']}/release", 200)
released = expect("student result", student, "GET", f"/student/results/{attempt['id']}", 200)
assert float(released["score"]) == 100.0
revision = expect("new structured revision", teacher, "POST", f"/questions/{ids[0]}/revisions", 201)
assert revision["snapshot"]["type"] == "multi_mcq"
expect("submit revision", teacher, "POST", f"/question-versions/{revision['id']}/submit", 200)
expect("approve revision", admin, "POST", f"/question-versions/{revision['id']}/decision", 200,
       {"approve": True, "note": "Verified synthetic fixture"})
old_score = subprocess.check_output(["mysql", "-N", "-u", "root", "maqna_cbt_dev", "-e",
                                     f"SELECT score FROM attempt_results WHERE attempt_id={attempt['id']}"], text=True).strip()
assert old_score == score
expect("student JSON export denied", student, "GET", "/questions/export/json", 403)
exported = expect("teacher JSON export", teacher, "GET", "/questions/export/json", 200)
assert exported["schema"] == "maqna-cbt.questions" and exported["version"] == 2
matching_export = next(question for question in exported["questions"] if question["type"] == "matching" and "Pengecoh" in json.dumps(question))
assert len(matching_export["matchingChoices"]) == 3 and matching_export["options"][0]["matchChoiceIndex"] in (0, 1)
payload = {"schema": "maqna-cbt.questions", "version": 1, "mode": "reject", "questions": [{
    "subjectCode": "MAT", "body": "S9 JSON " + str(time.time_ns()), "type": "multi_mcq", "weight": 2,
    "rubric": "", "explanation": "", "options": specs[0][1]}]}
expect("unknown JSON field denied", teacher, "POST", "/questions/import/json/preview", 422, {**payload, "evil": True})
expect("unknown JSON version denied", teacher, "POST", "/questions/import/json/preview", 422, {**payload, "version": 3})
expect("invalid second row rejects batch", teacher, "POST", "/questions/import/json/preview", 422,
       {**payload, "questions": payload["questions"] + [{**payload["questions"][0], "body": "Bad", "weight": -1}]})
preview = expect("JSON preview", teacher, "POST", "/questions/import/json/preview", 201, payload)
assert preview["ready"] == 1 and preview["rows"][0]["status"] == "ready"
expect("student JSON commit denied", student, "POST", f"/questions/import/json/{preview['id']}/commit", 403)
committed = expect("JSON commit", teacher, "POST", f"/questions/import/json/{preview['id']}/commit", 200)
assert committed["created"] == 1
expect("JSON commit idempotent", teacher, "POST", f"/questions/import/json/{preview['id']}/commit", 200)
expect("JSON duplicate denied", teacher, "POST", "/questions/import/json/preview", 422, payload)
skipped = expect("JSON skip preview", teacher, "POST", "/questions/import/json/preview", 201, {**payload, "mode": "skip"})
assert skipped["ready"] == 0 and skipped["skipped"] == 1
for variant in ("questions", "keys"):
    for fmt in ("pdf", "docx"):
        path = f"/questions/print/{fmt}?subjectId={math['id']}&variant={variant}"
        with teacher.open(ROOT + path) as response:
            document = response.read()
            assert response.status == 200 and len(document) > 100
            assert response.headers["Cache-Control"] == "no-store"
        if fmt == "pdf":
            assert document.startswith(b"%PDF")
        else:
            with zipfile.ZipFile(io.BytesIO(document)) as archive:
                xml = archive.read("word/document.xml").decode()
                assert "S9" in xml and ("Kunci soal" if variant == "keys" else "Naskah soal") in xml
                if variant == "questions":
                    assert "Kunci:" not in xml and "Rubrik:" not in xml
        print(f"{variant} {fmt}: PASS")
try:
    student.open(ROOT + "/questions/print/pdf")
    raise AssertionError("student print accepted")
except urllib.error.HTTPError as error:
    assert error.code == 403
draft = expect("key draft", teacher, "POST", "/questions", 201, {
    "subjectId": int(math["id"]), "body": "S9 key import " + str(time.time_ns()), "questionType": "mcq", "weight": 1,
    "options": [{"body": "one", "isCorrect": True}, {"body": "two", "isCorrect": False}], "status": "draft"})
with teacher.open(ROOT + '/questions/import/keys/template') as response:
    assert response.status == 200 and response.read().decode().startswith('question_id,type,key')
csv_body = f"question_id,type,key\n{draft['id']},mcq,B\n".encode()
upload_keys(student, 'keys.csv', csv_body, 403)
upload_keys(teacher, 'keys.csv', f"question_id,type,key\n{draft['id']},essay,B\n".encode(), 422)
workbook = Workbook()
sheet = workbook.active
sheet.append(['question_id', 'type', 'key'])
sheet.append([draft['id'], 'mcq', 'B'])
stream = io.BytesIO()
workbook.save(stream)
key_preview = upload_keys(teacher, 'keys.xlsx', stream.getvalue(), 201)
assert key_preview['rows'][0]['currentKey'] == 'A' and key_preview['rows'][0]['newKey'] == 'B'
expect('keys commit', teacher, 'POST', f"/questions/import/keys/{key_preview['id']}/commit", 200)
updated = expect('keys updated', teacher, 'GET', f"/questions/{draft['id']}", 200)
assert [int(option['is_correct']) for option in updated['options']] == [0, 1]
expect('keys idempotent', teacher, 'POST', f"/questions/import/keys/{key_preview['id']}/commit", 200)
expect('publish keyed', teacher, 'PATCH', f"/questions/{draft['id']}", 200, {'status': 'published'})
upload_keys(teacher, 'keys.csv', csv_body, 422)
changing = expect('key stale draft', teacher, 'POST', '/questions', 201, {
    'subjectId': int(math['id']), 'body': 'S9 stale key ' + str(time.time_ns()), 'questionType': 'mcq', 'weight': 1,
    'options': [{'body': 'one', 'isCorrect': True}, {'body': 'two', 'isCorrect': False}], 'status': 'draft'})
stale_file = f"question_id,type,key\n{changing['id']},mcq,B\n".encode()
stale_preview = upload_keys(teacher, 'keys.csv', stale_file, 201)
expect('publish before key commit', teacher, 'PATCH', f"/questions/{changing['id']}", 200, {'status': 'published'})
expect('stale key commit rejected', teacher, 'POST', f"/questions/import/keys/{stale_preview['id']}/commit", 409)
with teacher.open(ROOT + '/questions/import/docx/template') as response:
    docx_template = response.read()
    assert response.status == 200 and docx_template.startswith(b'PK')
docx_preview = upload_package(teacher, 'docx', docx_template, 'append', 201)
assert docx_preview['count'] == 1 and docx_preview['rows'][0]['status'] in ('ready', 'skip')
expect('DOCX commit', teacher, 'POST', f"/questions/import/docx/{docx_preview['id']}/commit", 200)
docx_files = {}
with zipfile.ZipFile(io.BytesIO(docx_template)) as archive:
    for name in archive.namelist():
        docx_files[name] = archive.read(name)
docx_image_body = 'Pertanyaan dengan gambar ' + str(time.time_ns())
docx_files['word/document.xml'] = docx_files['word/document.xml'].replace(
    b'Tulis pertanyaan di sini', docx_image_body.encode()).replace(
    b'<w:p><w:r><w:t xml:space="preserve">END</w:t></w:r></w:p>',
    b'<w:p><w:r><w:drawing><a:blip xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" '
    b'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" r:embed="rIdImage"/>'
    b'</w:drawing></w:r></w:p><w:p><w:r><w:t xml:space="preserve">END</w:t></w:r></w:p>')
docx_files['word/_rels/document.xml.rels'] = b'<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image1.png"/></Relationships>'
docx_files['word/media/image1.png'] = tiny_png()
docx_media = io.BytesIO()
with zipfile.ZipFile(docx_media, 'w', zipfile.ZIP_DEFLATED) as archive:
    for name, content in docx_files.items():
        archive.writestr(name, content)
docx_image_preview = upload_package(teacher, 'docx', docx_media.getvalue(), 'append', 201)
assert docx_image_preview['rows'][0]['status'] == 'ready'
expect('DOCX image commit', teacher, 'POST', f"/questions/import/docx/{docx_image_preview['id']}/commit", 200)
docx_rows = expect('find DOCX image question', teacher, 'GET', '/questions?search=' + docx_image_body.replace(' ', '%20'), 200)
docx_row = expect('DOCX image detail', teacher, 'GET', f"/questions/{docx_rows[0]['id']}", 200)
assert len(docx_row['assets']) == 1 and docx_row['assets'][0]['mime_type'] == 'image/png'
bad_docx = io.BytesIO()
with zipfile.ZipFile(bad_docx, 'w', zipfile.ZIP_DEFLATED) as archive:
    for name, content in docx_files.items():
        if name == 'word/document.xml':
            content = content.replace(b'SUBJECT: MAT', b'UNKNOWN: MAT')
        archive.writestr(name, content)
upload_package(teacher, 'docx', bad_docx.getvalue(), 'append', 422)
with teacher.open(ROOT + '/questions/package.zip') as response:
    backup = response.read()
    assert response.status == 200 and backup.startswith(b'PK')
with zipfile.ZipFile(io.BytesIO(backup)) as archive:
    manifest = json.loads(archive.read('manifest.json'))
    assert manifest['schema'] == 'maqna-cbt.question-package' and manifest['version'] == 1
zip_preview = upload_package(teacher, 'zip', backup, 'append', 201)
assert zip_preview['count'] == len(manifest['questions'])
expect('ZIP append commit', teacher, 'POST', f"/questions/import/zip/{zip_preview['id']}/commit", 200)
upload_package(student, 'zip', backup, 'append', 403)
package_question = {"subjectCode": "MAT", "body": "S9 media package " + str(time.time_ns()),
                    "type": "mcq", "weight": 1, "rubric": "", "explanation": "",
                    "options": [{"body": "a", "isCorrect": True, "matchBody": None},
                                {"body": "b", "isCorrect": False, "matchBody": None}],
                    "assetPaths": ["media/one.png"]}
def package_bytes(question, image_name):
    output = io.BytesIO()
    with zipfile.ZipFile(output, 'w', zipfile.ZIP_DEFLATED) as archive:
        archive.writestr('manifest.json', json.dumps({'schema': 'maqna-cbt.question-package', 'version': 1, 'questions': [question]}))
        archive.writestr(image_name, tiny_png())
    return output.getvalue()
created_preview = upload_package(teacher, 'zip', package_bytes(package_question, 'media/one.png'), 'append', 201)
assert created_preview['rows'][0]['status'] == 'ready'
created_package = expect('ZIP media commit', teacher, 'POST', f"/questions/import/zip/{created_preview['id']}/commit", 200)
assert created_package['created'] == 1
listed = expect('find ZIP question', teacher, 'GET', '/questions?search=' + package_question['body'].replace(' ', '%20'), 200)
media_question = expect('ZIP question detail', teacher, 'GET', f"/questions/{listed[0]['id']}", 200)
assert len(media_question['assets']) == 1
old_asset = int(media_question['assets'][0]['id'])
with teacher.open(ROOT + f'/question-assets/{old_asset}') as response:
    assert response.read().startswith(b'\x89PNG')
replacement = {**package_question, 'options': [{"body": "changed", "isCorrect": True, "matchBody": None},
                                                {"body": "b", "isCorrect": False, "matchBody": None}],
               'assetPaths': ['media/two.png']}
replaced_preview = upload_package(teacher, 'zip', package_bytes(replacement, 'media/two.png'), 'replace', 201)
assert replaced_preview['rows'][0]['status'] == 'replace'
replaced_package = expect('ZIP replace commit', teacher, 'POST', f"/questions/import/zip/{replaced_preview['id']}/commit", 200)
assert replaced_package['replaced'] == 1
media_question = expect('ZIP replaced detail', teacher, 'GET', f"/questions/{media_question['id']}", 200)
assert media_question['options'][0]['body'] == 'changed' and len(media_question['assets']) == 1
try:
    teacher.open(ROOT + f'/question-assets/{old_asset}')
    raise AssertionError('archived media still served')
except urllib.error.HTTPError as error:
    assert error.code == 404
expect('publish replaced question', teacher, 'PATCH', f"/questions/{media_question['id']}", 200, {'status': 'published'})
upload_package(teacher, 'zip', package_bytes(replacement, 'media/two.png'), 'replace', 422)
bad_stream = io.BytesIO()
with zipfile.ZipFile(bad_stream, 'w') as archive:
    archive.writestr('../evil', 'bad')
    archive.writestr('manifest.json', json.dumps({'schema': 'maqna-cbt.question-package', 'version': 1, 'questions': []}))
unsafe = upload_package(teacher, 'zip', bad_stream.getvalue(), 'append', 422)
assert 'tidak aman' in unsafe['error']['message'] or 'Path' in unsafe['error']['message'], unsafe
bomb_stream = io.BytesIO()
with zipfile.ZipFile(bomb_stream, 'w', zipfile.ZIP_DEFLATED) as archive:
    archive.writestr('manifest.json', json.dumps({'schema': 'maqna-cbt.question-package', 'version': 1, 'questions': []}))
    archive.writestr('media/bomb.png', b'A' * 1024 * 1024)
bomb = upload_package(teacher, 'zip', bomb_stream.getvalue(), 'append', 422)
assert 'kompresi' in bomb['error']['message'] or 'besar' in bomb['error']['message'], bomb
print("S9 structured create, publish, snapshot, shuffle, autosave, resume and score: PASS")
