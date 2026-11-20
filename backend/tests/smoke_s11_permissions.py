"""Granular staff permission denial, immediate enforcement, and school scope."""
import http.cookiejar, json, urllib.error, urllib.request
ROOT='http://127.0.0.1:8081/api/v1'
def client(): return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def call(agent,method,path,payload=None):
    headers={'Accept':'application/json'}
    if method!='GET': headers['X-CSRF-TOKEN']=call(agent,'GET','/auth/csrf')[1]['data']['token']
    if payload is not None: headers['Content-Type']='application/json'
    req=urllib.request.Request(ROOT+path,data=None if payload is None else json.dumps(payload).encode(),method=method,headers=headers)
    try: res=agent.open(req)
    except urllib.error.HTTPError as error: res=error
    return res.status,json.load(res)
def expect(agent,method,path,status,payload=None):
    actual,body=call(agent,method,path,payload)
    assert actual==status,(method,path,actual,body)
    return body.get('data')
admin,teacher,student=client(),client(),client()
expect(admin,'POST','/auth/login',200,{'identity':'admin_demo','password':'FixtureOnly123!'})
expect(teacher,'POST','/auth/login',200,{'identity':'teacher_demo','password':'FixtureOnly123!'})
expect(student,'POST','/auth/login',200,{'identity':'student_demo','password':'FixtureOnly123!'})
teachers=expect(admin,'GET','/teachers?perPage=100',200)
target=next(int(row['user_id']) for row in teachers if row['username']=='teacher_demo')
path=f'/users/{target}/permissions'
expect(student,'GET',path,403)
expect(teacher,'GET',path,403)
rows=expect(admin,'GET',path,200)
assert any(row['permission']=='reports.view' for row in rows)
try:
    expect(admin,'PUT',path,200,{'permission':'reports.view','allowed':False})
    me=expect(teacher,'GET','/auth/me',200)
    assert 'reports.view' not in me['permissions'],me['permissions']
    expect(teacher,'GET','/reports/exams',403)
    expect(admin,'PUT',path,422,{'permission':'school.manage','allowed':True})
finally:
    expect(admin,'PUT',path,200,{'permission':'reports.view','allowed':True})
expect(teacher,'GET','/reports/exams',200)
print('S11 granular permissions, admin-only edit, and immediate enforcement PASS')
