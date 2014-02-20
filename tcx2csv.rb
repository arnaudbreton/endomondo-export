require 'rubygems'
require 'find'
require 'hpricot'
require 'csv'

path = ARGV[0]

puts "Time,Distance,TotalTimeMinutes,MaximumSpeed,Calories,AvgHR,MaxHR,AvgSpeed"
Dir.glob(path) do |file|
    next if file == '.' or file == '..'
    doc = Hpricot::XML(File.read(file))
    csv = CSV.generate do |csv|
      (doc/:Lap).each do |lap|
        next if lap.at('DistanceMeters').innerText.to_f.to_i < 1000
        record = []

        record << lap['StartTime']
        record << lap.at('DistanceMeters').innerText.to_f.to_i
        record << Time.at(lap.at('TotalTimeSeconds').innerText.to_f).utc.strftime("%M:%S")

        if lap.at('MaximumSpeed')
          record << (lap.at('MaximumSpeed').innerText.to_f*3.6).round(2)
        else
          record << ''
        end

        record << lap.at('Calories').innerText

        if lap.at('AverageHeartRateBpm') 
          record << lap.at('AverageHeartRateBpm').at('Value').innerText
        else
          record << ''
        end

        if lap.at('MaximumHeartRateBpm')
          record << lap.at('MaximumHeartRateBpm').at('Value').innerText
        else
          record << ''
        end

        if lap.search('> Extensions') && lap.search('> Extensions').at('LX') && lap.search('> Extensions').at('LX').at('AvgSpeed')
          record << (lap.search('> Extensions').at('LX').at('AvgSpeed').innerText.to_f * 3.6).round(2)
        else
            record << ''
        end

        puts record.join(',')
      end
    end
end