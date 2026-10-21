# GitHub Theme Updater

ووردبريس بلاجن بسيط: بيفحص GitHub Releases لثيم معيّن، ويحدّثه تلقائيًا على
الموقع المباشر — يدعم ريبو عام أو خاص، من غير أي كود يتكتب جوة الثيم نفسه.

**ليه بلاجن مش كود جوة الثيم؟** لأن الكود اللي بيعمل التحديث لازم يكون
**ثابت ومش جوة الفولدر اللي بيتغيّر** وقت التحديث. لو حطيت منطق التحديث
جوة الثيم نفسه، بتخلّي الكود يحاول يستبدل الفولدر اللي هو شغال منه –
نمط هش. البلاجن منفصل تمامًا وما يتأثرش بتحديث الثيم.

بُني بواسطة [Abdulrahman Salah](https://a-salah.dev).

## التنصيب

1. حمّل أو clone الريبو ده
2. ارفعه كـ zip في **Plugins → Add New Plugin → Upload Plugin**، أو ضيفه
   في `wp-content/plugins/github-theme-updater`
3. فعّله
4. روح **Settings → GitHub Theme Updater** واملأ:
   - **Theme slug**: اسم فولدر الثيم بالظبط (من غير نقطة!)
   - **GitHub username/org** و **Repository name**
   - **Visibility**: Public (مفيش توكن) أو Private (لازم توكن)
   - **GitHub token**: لو الريبو خاص بس — Fine-grained PAT بصلاحية قراءة
     Contents + Releases على الريبو ده لوحده
   - **Force automatic background updates**: فعّلها لو عايز التحديث
     يحصل لوحده من غير ضغط زرار

## المتطلبات على الريبو

الثيم لازم يكون له GitHub Action بينشر Release فيه zip باسم
`{theme_slug}.zip` على كل تاج. شوف [wp-theme-auto-update-template](https://github.com/Abdulrahman14Salah/wp-theme-auto-update-template)
للقالب الجاهز.

## متوافق مع

أي ثيم ووردبريس، بس اسم الفولدر لازم يكون من غير نقطة (`.`) — WordPress
core بيشيل النقط لما بينظّف اسماء الثيمات في أكتر من مكان (AJAX التحديث،
كرون التحديث التلقائي)، وده بيكسر التحديث بصمت.
