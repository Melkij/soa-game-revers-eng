Reverse engineering datafiles from old game soldiers of anarchy

Попытка исследования данных старой игры "Солдаты Анархии".
На данной момент фокус на файлах игровых миссий.

Цели:
 1. парсер любого *.mis (допустимо с неизвестными бинарными кусками)
 2. возможность по результату парсера сгенерировать бинарный mis, идентичный исходному
 3. возможность внести изменения в в результат рарсера и корректно сохранить
 4. создание новой миссии только по описанию в этом классе - т.е. непонятных бинарных кусков уже не должно быть

Состояние: 
 * несколько сотен тестовых миссий в testmis/* могут быть разобраны в осмысленные структуры либо с неизвестными бинарными пропусками. Но с корректным поиском конца файла.
 * пересохранённые файлы оригинальной кампании могут быть прочитаны до начала скриптов - т.е. все объекты на карте распознаются

Не реализованы:
 - скрипты миссии
 - диалоги
 - внутриигровые фильмы
 - очень много неизвестных бинарных блоков